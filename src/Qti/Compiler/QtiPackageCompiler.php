<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Compiler;

use App\Cache\CacheTrait;
use App\ContentServiceApi\Gateway\ContentServiceApiGateway;
use App\Environment\FeatureFlagAdapterInterface;
use App\Manager\PciAssetManager;
use App\Qti\Result\QtiAssessmentItemRefMappingResult;
use App\Qti\Result\QtiPackageCompilationResult;
use App\Qti\Result\QtiTestCompilationResult;
use App\Traits\FilesystemTrait;
use DomainException;
use DOMDocument;
use DOMNodeList;
use DOMXPath;
use Exception;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\FilesystemWriter;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use OAT\Library\QtiItemJsonCompilation\Compiling\ItemCompiler;
use Psr\Cache\CacheException;
use Psr\Log\LoggerInterface;
use qtism\data\AssessmentItemRef;
use qtism\data\content\interactions\TextFormat;
use qtism\data\state\ResponseValidityConstraint;
use qtism\data\storage\xml\XmlCompactDocument;
use qtism\data\storage\xml\XmlDocument;
use qtism\data\storage\xml\XmlStorageException;
use Symfony\Contracts\Cache\CacheInterface;
use Throwable;

class QtiPackageCompiler
{
    use CacheTrait;
    use FilesystemTrait;
    private const FEATURE_FLAG_COMPILE_RESPONSE_MAPPINGS_ENABLED = 'COMPILE_RESPONSE_MAPPINGS_ENABLED';

    public const IMS_MANIFEST_FILE_NAME = 'imsmanifest.xml';
    public const COMPACT_TEST_FILE_NAME = 'compact-test.xml';
    public const JSON_ITEM_FILE_NAME = 'item.json';
    public const JSON_ITEM_METADATA_ELEMENTS_FILE_NAME = 'metadataElements.json';
    public const JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME = 'portableElements.json';
    public const JSON_ITEM_VARIABLE_ELEMENTS_FILE_NAME = 'variableElements.json';
    private const ITEM_TITLE_LENGTH = 1024;
    private const SCALES = 'scales';

    private array $compilationReports;

    public function __construct(
        private readonly FilesystemOperator $qtiPackageExtractorStorage,
        private readonly FilesystemWriter $qtiCompiledDeliveriesStorage,
        private readonly ItemCompiler $itemCompiler,
        private readonly LoggerInterface $logger,
        private readonly RubricBlocksCompiler $rubricBlocksCompiler,
        private readonly LoggerInterface $auditPlatformLogger,
        protected CacheInterface $cache,
        private readonly PciAssetManager $pciAssetManager,
        private readonly ContentServiceApiGateway $contentServiceApiGateway,
        private readonly FeatureFlagAdapterInterface $featureFlagAdapter,
    ) {
    }

    public function compile(
        string $compilationId,
        string $packagePath,
        string $tenantId,
        ?string $localePath = null,
    ): QtiPackageCompilationResult {
        $this->compilationReports = [];
        $imsManifestPath = $this->buildPathFor($packagePath, self::IMS_MANIFEST_FILE_NAME);

        try {
            $testResources = $this->getTestResourcesFromManifestFile($imsManifestPath);

            if (1 !== $testResources->length) {
                throw new DomainException('No or more than one tests found in provided package');
            }

            $extractedQtiTestRelativePath = $testResources->item(0)->getAttribute('href');

            $qtiTestCompilationResult = $this->compileTest(
                $compilationId,
                $extractedQtiTestRelativePath,
                $this->buildPathFor($packagePath, $extractedQtiTestRelativePath),
                $packagePath,
                $tenantId,
                $localePath,
            );

            // Save imsmanifest.xml alongside the compact test in the compiled deliveries storage
            $manifestPath = $this->buildPathFor($compilationId, $localePath, self::IMS_MANIFEST_FILE_NAME);
            $readStream = $this->qtiPackageExtractorStorage->readStream($manifestPath);
            $this->qtiCompiledDeliveriesStorage->writeStream($manifestPath, $readStream);
            if (is_resource($readStream)) {
                fclose($readStream);
            }

            // Copy scales JSON files from tests to scales directory
            $manifestPathForScales = $this->buildPathFor($compilationId, $localePath, self::IMS_MANIFEST_FILE_NAME);
            $this->copyScalesFiles($compilationId, $localePath, $manifestPathForScales);

            $this->qtiPackageExtractorStorage->deleteDirectory($this->buildPathFor($compilationId, $localePath));

            $this->report(sprintf('[%s] package compilation success', $compilationId));

            return new QtiPackageCompilationResult(
                true,
                $this->compilationReports,
                $qtiTestCompilationResult->getAssessmentItemsRefMapping(),
                $qtiTestCompilationResult->getCompactTestDocumentPath(),
            );
        } catch (Exception $exception) {
            $message = sprintf('[%s] package compilation failure', $compilationId);

            $this->auditPlatformLogger->error($message);

            $this->reportException($message, $exception->getPrevious() ?: $exception);

            return new QtiPackageCompilationResult(false, $this->compilationReports);
        }
    }

    private function compileTest(
        string $compilationId,
        string $extractedQtiTestRelativePath,
        string $extractedQtiTestPath,
        string $packagePath,
        string $tenantId,
        ?string $localePath = null,
    ): QtiTestCompilationResult {
        try {
            $extractedQtiTestFolderRelativePath = dirname($extractedQtiTestRelativePath);
            $compactTestDocument = $this->createCompactTestFromQtiXml($extractedQtiTestPath);
            $compactTestDocumentPath = $this->buildPathFor($compilationId, $localePath, self::COMPACT_TEST_FILE_NAME);

            // Compile rubricBlocks and serialize on disk.
            $this->rubricBlocksCompiler->compileRubricBlocks(
                $compactTestDocument,
                $compilationId,
                $extractedQtiTestFolderRelativePath,
                $localePath,
            );

            $this->patchConstraints($compactTestDocument);
            $this->saveInCache(
                md5($compactTestDocumentPath),
                $compactTestDocument,
            );

            $itemReferences = $compactTestDocument->getDocumentComponent()->getComponentsByClassName('assessmentItemRef');

            if (0 === $itemReferences->count()) {
                throw new DomainException('No items found in provided package');
            }

            $assessmentItemRefMapping = [];

            foreach ($itemReferences as $itemReference) {
                $assessmentItemRefMapping[$itemReference->getIdentifier()] =
                    $this->getAssessmentItemRefMapping($this->buildPathFor(
                        $packagePath,
                        dirname($extractedQtiTestRelativePath),
                        $itemReference->getHref(),
                    ))->normalize();

                // Compile item data once and reuse for both response mappings and item compilation
                $itemData = $this->getCompiledItemData(
                    $compilationId,
                    $extractedQtiTestFolderRelativePath,
                    $itemReference,
                    $localePath,
                );

                if ($this->featureFlagAdapter->isEnabled($tenantId, self::FEATURE_FLAG_COMPILE_RESPONSE_MAPPINGS_ENABLED)) {
                    $assessmentItemRefMapping[$itemReference->getIdentifier()]['responseMappings'] =
                        $this->extractResponseMappingsFromItemData($itemData);
                }

                /** @var AssessmentItemRef $itemRef */
                $this->compileItem(
                    $compilationId,
                    $extractedQtiTestFolderRelativePath,
                    $itemReference,
                    $itemData,
                    $tenantId,
                    $localePath,
                );
            }

            $this->qtiCompiledDeliveriesStorage->write(
                $compactTestDocumentPath,
                $compactTestDocument->saveToString(),
            );

            $this->report(sprintf('[%s] test compilation success', $compilationId));

            return new QtiTestCompilationResult($compactTestDocumentPath, $assessmentItemRefMapping);
        } catch (Exception $exception) {
            $message = sprintf('test "%s" compilation failure', $extractedQtiTestRelativePath);

            throw new DomainException($message, 0, $exception);
        }
    }

    /**
     * Compiles item data (reads XML and processes it). Returns the compiled data for reuse.
     */
    private function getCompiledItemData(
        string $compilationId,
        string $extractedQtiTestFolderRelativePath,
        AssessmentItemRef $item,
        ?string $localePath = null,
    ): array {
        $itemSourcePath = $this->buildPathFor($compilationId, $localePath, $extractedQtiTestFolderRelativePath, $item->getHref());
        $itemDestinationPath = $this->buildPathFor($compilationId, $localePath, $item->getIdentifier());

        $qtiItemXmlContent = $this->qtiPackageExtractorStorage->read($itemSourcePath);

        return $this->removeAnchorsForPatterns(
            $this->itemCompiler->getCompiledItemDataFor($qtiItemXmlContent, dirname($itemSourcePath), $itemDestinationPath),
        );
    }

    private function compileItem(
        string $compilationId,
        string $extractedQtiTestFolderRelativePath,
        AssessmentItemRef $item,
        array $itemData,
        string $tenantId,
        ?string $localePath = null,
    ): void {
        try {
            $itemSourcePath = $this->buildPathFor($compilationId, $localePath, $extractedQtiTestFolderRelativePath, $item->getHref());
            $itemDestinationPath = $this->buildPathFor($compilationId, $localePath, $item->getIdentifier());

            $titleLength = strlen($itemData['core']['data']['attributes']['title']);
            if ($titleLength > self::ITEM_TITLE_LENGTH) {
                throw new DomainException(
                    sprintf(
                        'Item %s title is too long, %s is maximum, %s provided',
                        $item->getIdentifier(),
                        self::ITEM_TITLE_LENGTH,
                        $titleLength,
                    ),
                );
            }

            $this->qtiCompiledDeliveriesStorage->write(
                $this->buildPathFor($itemDestinationPath, self::JSON_ITEM_FILE_NAME),
                json_encode($itemData['core']),
            );

            $this->contentServiceApiGateway->uploadItemContent(
                $this->buildPathFor($itemDestinationPath, self::JSON_ITEM_FILE_NAME),
                json_encode($itemData['core']),
                $tenantId,
            );

            $this->saveInCache(
                md5($this->buildPathFor($itemDestinationPath, self::JSON_ITEM_FILE_NAME)),
                $itemData['core'],
            );

            $this->qtiCompiledDeliveriesStorage->write(
                $this->buildPathFor($itemDestinationPath, self::JSON_ITEM_METADATA_ELEMENTS_FILE_NAME),
                json_encode([]),
            );

            /** Save PCI resources into the storage and modify its paths */
            $itemData['portableElements'] = $this->pciAssetManager->compileAssets(
                $itemData['portableElements'],
                dirname($itemSourcePath),
                $compilationId,
            );

            $this->qtiCompiledDeliveriesStorage->write(
                $this->buildPathFor($itemDestinationPath, self::JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME),
                json_encode($itemData['portableElements']),
            );

            $this->saveInCache(
                md5($this->buildPathFor($itemDestinationPath, self::JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME)),
                $itemData['portableElements'],
            );

            $this->qtiCompiledDeliveriesStorage->write(
                $this->buildPathFor($itemDestinationPath, self::JSON_ITEM_VARIABLE_ELEMENTS_FILE_NAME),
                json_encode($itemData['variable']),
            );

            $item->setHref(
                $this->buildPathFor('.', $item->getIdentifier(), self::JSON_ITEM_FILE_NAME),
            );

            $this->report(sprintf('[%s] item %s compilation success', $compilationId, $item->getIdentifier()));
        } catch (Exception $exception) {
            $message = sprintf('item "%s" compilation failure', $item->getIdentifier());

            throw new DomainException($message, 0, $exception);
        }
    }

    private function saveInCache(string $cacheKey, $data): void
    {
        try {
            $this->setInCache($cacheKey, $data, TestSessionAccessorFactory::CACHE_DEFAULT_TTL);
        } catch (CacheException $exception) {
            $this->logger->error($exception->getMessage(), compact('exception'));
        }
    }

    private function report(string $message): void
    {
        $this->compilationReports[] = ['type' => 'success', 'message' => $message];

        $this->auditPlatformLogger->info($message);
    }

    private function reportException(string $message, Throwable $exception): void
    {
        // display a chain of error messages
        do {
            $errorMessage = sprintf('%s: %s', $message, $exception->getMessage());
            $this->compilationReports[] = ['type' => 'error', 'message' => $errorMessage];
            $this->logger->error($errorMessage, compact('exception'));
            $message = $exception->getMessage();
        } while ($exception = $exception->getPrevious());
    }

    private function getTestResourcesFromManifestFile(string $manifestPath): DOMNodeList
    {
        $domDocument = new DOMDocument('1.0', 'UTF-8');

        $domDocument->load($manifestPath);
        $domXPath = new DOMXPath($domDocument);

        return $domXPath->query("//*[local-name()='resource' and contains(@type, 'imsqti_test_xmlv2p')]");
    }

    private function getAssessmentItemRefFromQtiDefinitionFile(string $testDefinitionPath): DOMNodeList
    {
        $domDocument = new DOMDocument('1.0', 'UTF-8');

        $domDocument->load($testDefinitionPath);
        $domXPath = new DOMXPath($domDocument);

        return $domXPath->query("//*[local-name()='assessmentItem']");
    }

    /**
     * @throws XmlStorageException
     */
    private function createCompactTestFromQtiXml(string $filePath): XmlCompactDocument
    {
        $xmlDocument = new XmlDocument();

        $xmlDocument->load($filePath, true);

        return XmlCompactDocument::createFromXmlAssessmentTestDocument($xmlDocument, null, '2.2');
    }

    private function getAssessmentItemRefMapping(string $itemSourcePath): QtiAssessmentItemRefMappingResult
    {

        $assessmentItem = $this->getAssessmentItemRefFromQtiDefinitionFile($itemSourcePath);

        if (1 !== $assessmentItem->length) {
            throw new DomainException(sprintf('No items found in %s', $itemSourcePath));
        }

        $assessmentItemNode = $assessmentItem->item(0);
        $identifier = $assessmentItemNode->attributes->getNamedItem('identifier')->nodeValue;

        $label = null !== $assessmentItemNode->attributes->getNamedItem('label')
            ? $assessmentItemNode->attributes->getNamedItem('label')->nodeValue
            : null;

        $title = null !== $assessmentItemNode->attributes->getNamedItem('title')
            ? $assessmentItemNode->attributes->getNamedItem('title')->nodeValue
            : null;

        return new QtiAssessmentItemRefMappingResult($identifier, $label, $title);
    }

    /**
     * Extracts response mappings from already-compiled item data.
     * Returns empty array if the item structure is unexpected (no interactions, missing data).
     */
    private function extractResponseMappingsFromItemData(array $itemData): array
    {
        $itemCoreData = $itemData['core']['data'] ?? [];
        $itemVariableData = $itemData['variable'] ?? [];

        $responses = $itemCoreData['responses'] ?? [];
        $elements = $itemCoreData['body']['elements'] ?? [];

        if (!is_array($responses) || !is_array($elements)) {
            return [];
        }

        $responsesByIdentifier = array_column($responses, null, 'identifier');

        $itemResponsesMapping = [];
        foreach ($elements as $element) {
            $attributes = $element['attributes'] ?? [];
            if (!is_array($attributes)) {
                continue;
            }

            $responseIdentifier = $attributes['responseIdentifier'] ?? '';
            if (!$responseIdentifier) {
                continue;
            }

            $variableData = [];
            if (isset($responsesByIdentifier[$responseIdentifier])) {
                $serial = $responsesByIdentifier[$responseIdentifier]['serial'] ?? null;
                $variableData = $serial !== null ? ($itemVariableData[$serial] ?? []) : [];
            }

            $itemResponsesMapping[$responseIdentifier] = [
                'identifier' => $responseIdentifier,
                'qtiClass' => $element['qtiClass'] ?? '',
                'responseMapping' => $variableData['mapping'] ?? [],
                'correctResponses' => $variableData['correctResponses'] ?? [],
                'matchingTemplate' => $variableData['howMatch'] ?? '',
            ];
        }

        return $itemResponsesMapping;
    }

    private function removeAnchorsForPatterns(array $itemData): array
    {
        if (!is_array($itemData['core']['data']['body']['elements'] ?? '')) {
            return $itemData;
        }

        foreach ($itemData['core']['data']['body']['elements'] as &$testElement) {
            if (isset($testElement['body']['elements']) && is_array($testElement['body']['elements'])) {
                foreach ($testElement['body']['elements'] as &$itemElement) {
                    if (is_array($itemElement['attributes']) && isset($itemElement['attributes']['patternMask'])) {
                        $itemElement['attributes']['patternMask'] = $this->sanitizePattern(
                            $itemElement['attributes']['patternMask'],
                        );
                    }
                }
            }

            if (is_array($testElement['attributes']) && isset($testElement['attributes']['patternMask'])) {
                $testElement['attributes']['patternMask'] = $this->sanitizePattern(
                    $testElement['attributes']['patternMask'],
                );
            }
        }

        return $itemData;
    }

    private function sanitizePattern(string $pattern): string
    {
        return rtrim(ltrim($pattern, '^'), '$');
    }

    private function patchConstraints(XmlCompactDocument $compactTestDocument): void
    {
        /** @var ResponseValidityConstraint $constraint */
        foreach (
            $compactTestDocument->getDocumentComponent()->getComponentsByClassName(
                'responseValidityConstraint',
            ) as $constraint
        ) {
            $extraData = $constraint->getExtraData();
            $constraint->setPatternMask(
                empty($extraData['qtiClassName'])
                || $extraData['qtiClassName'] !== 'extendedTextInteraction'
                || $extraData['options']['format'] !== TextFormat::XHTML
                    ? $this->sanitizePattern($constraint->getPatternMask())
                    : '',
            );
        }
    }

    /**
     * Copies scales JSON files from test resources to a scales directory in the compiled deliveries storage.
     *
     * @throws FilesystemException
     */
    private function copyScalesFiles(string $compilationId, ?string $localePath, string $manifestPath): void
    {
        $scaleFiles = $this->getScalesFilesFromManifest($manifestPath);

        if (empty($scaleFiles)) {
            return;
        }

        $scalesDestinationDir = $this->buildPathFor($compilationId, $localePath, self::SCALES);
        foreach ($scaleFiles as $scaleFile) {
            $sourcePath = $this->buildPathFor($compilationId, $localePath, $scaleFile);
            $destinationPath = $this->buildPathFor($scalesDestinationDir, basename($scaleFile));

            if (!$this->qtiPackageExtractorStorage->fileExists($sourcePath)) {
                $this->logger->warning(
                    sprintf('Scale file not found: %s', $sourcePath),
                    ['compilationId' => $compilationId, 'scaleFile' => $scaleFile],
                );
                continue;
            }

            $this->copyScaleFile($sourcePath, $destinationPath, $compilationId, $scaleFile);
        }
    }

    /**
     * Extracts scale JSON file paths from the imsmanifest.xml file.
     * Returns an array of relative file paths (e.g., 'tests/.../scales/OUTCOME_5.json').
     *
     * @return array<string>
     * @throws FilesystemException
     */
    private function getScalesFilesFromManifest(string $manifestPath): array
    {
        $manifestContent = $this->qtiPackageExtractorStorage->read($manifestPath);
        $domDocument = new DOMDocument('1.0', 'UTF-8');
        $domDocument->loadXML($manifestContent);
        $domXPath = new DOMXPath($domDocument);

        // Find all test resources
        $testResources = $domXPath->query("//*[local-name()='resource' and contains(@type, 'imsqti_test_xmlv2p')]");

        $scaleFiles = [];

        foreach ($testResources as $testResource) {
            // Find all file elements within this test resource that reference scales JSON files
            $fileNodes = $domXPath->query(".//*[local-name()='file' and contains(@href, '/scales/') and contains(@href, '.json')]", $testResource);

            foreach ($fileNodes as $fileNode) {
                $href = $fileNode->getAttribute('href');
                if ($href && !isset($scaleFiles[$href])) {
                    $scaleFiles[$href] = $href;
                }
            }
        }

        return $scaleFiles;
    }

    /**
     * @throws FilesystemException
     */
    public function copyScaleFile(string $sourcePath, string $destinationPath, string $compilationId, string $scaleFile): void
    {
        try {
            $readStream = $this->qtiPackageExtractorStorage->readStream($sourcePath);
            $this->qtiCompiledDeliveriesStorage->writeStream($destinationPath, $readStream);

            $this->report(sprintf('[%s] copied scale file: %s', $compilationId, basename($scaleFile)));
        } catch (FilesystemException $exception) {
            $this->logger->error(
                sprintf('[%s] failed to copy scales file: %s', $compilationId, basename($scaleFile)),
                ['exception' => $exception],
            );

            // Scale files are needed - re-throw exception in case copying is failed.
            throw $exception;
        } finally {
            if (isset($readStream) && is_resource($readStream)) {
                fclose($readStream);
            }
        }
    }
}
