<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Compiler;

use App\Generator\UuidGenerator;
use App\Qti\Render\XhtmlRenderingEngine;
use App\Traits\FilesystemTrait;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemWriter;
use OAT\Library\QtiItemJsonCompilation\Asset\AssetDownloader;
use OAT\Library\QtiItemJsonCompilation\Exception\CompilationException;
use Psr\Log\LoggerInterface;
use qtism\data\content\BodyElement;
use qtism\data\content\RubricBlock;
use qtism\data\content\Stylesheet;
use qtism\data\content\StylesheetCollection;
use qtism\data\content\xhtml\Img;
use qtism\data\content\xhtml\ObjectElement;
use qtism\data\QtiComponent;
use qtism\data\storage\xml\XmlCompactDocument;
use qtism\data\storage\xml\XmlDocument;
use qtism\data\storage\xml\XmlStorageException;
use qtism\runtime\rendering\markup\MarkupPostRenderer;
use qtism\runtime\rendering\RenderingException;

class RubricBlocksCompiler
{
    use FilesystemTrait;

    public const RUBRIC_BLOCKS_FOLDER_NAME = 'rubricBlocks';

    public function __construct(
        private readonly FilesystemWriter $qtiCompiledDeliveriesStorage,
        private readonly LoggerInterface $auditPlatformLogger,
        private readonly XhtmlRenderingEngine $renderingEngine,
        private readonly MarkupPostRenderer $markupPostRenderer,
        private readonly UuidGenerator $generator,
        private readonly AssetDownloader $assetDownloader,
    ) {
    }

    /**
     * @throws CompilationException
     * @throws FilesystemException
     * @throws XmlStorageException
     * @throws RenderingException
     */
    public function compileRubricBlocks(
        XmlCompactDocument $compactTestDocument,
        string $compilationId,
        string $extractedQtiTestFolderRelativePath,
        ?string $localePath = null,
    ): void {
        //clean the xml header
        $this->markupPostRenderer->cleanUpXmlDeclaration(true);

        $explodedRubricBlocks = $compactTestDocument->explodeRubricBlocks();

        foreach ($explodedRubricBlocks as $href => $rubricBlock) {
            $rubricBlocksContent[$href] = $this->createRubricBlockXML($rubricBlock);
        }

        $assessmentTest = $compactTestDocument->getDocumentComponent();
        $rubricBlockRefs = $assessmentTest->getComponentsByClassName('rubricBlockRef');

        $this->auditPlatformLogger->info(
            sprintf('[%s] Rubric blocks extracted successfully', $compilationId),
        );

        foreach ($rubricBlockRefs as $rubricRef) {
            $rubricRefHref = $rubricRef->getHref();
            $rubricDoc = new XmlDocument();

            $rubricDoc->loadFromString($rubricBlocksContent[$rubricRefHref]);

            /** @var RubricBlock $rubric */
            $rubric = $rubricDoc->getDocumentComponent();

            $this->processStyleSheets($rubric, $compilationId, $extractedQtiTestFolderRelativePath, $localePath);
            $this->copyRemoteResources($rubric, $extractedQtiTestFolderRelativePath, $compilationId, $localePath);

            /** @var BodyElement $rubric */
            if ($rubric->hasId() === false) {
                $rubric->setId('tao' . $this->generator->generate());
            }

            $domRendering = $this->renderingEngine->render($rubric);
            $mainStringRendering = $this->markupPostRenderer->render($domRendering);
            $styleRendering = $this->renderingEngine->getStylesheets();
            $mainStringRendering = $styleRendering->ownerDocument->saveXML($styleRendering) . $mainStringRendering;
            $rubricNewHref = basename(str_replace('.xml', '.html.twig', $rubricRefHref));

            $this->qtiCompiledDeliveriesStorage->write(
                $this->buildPathFor($compilationId, $localePath, static::RUBRIC_BLOCKS_FOLDER_NAME, $rubricNewHref),
                $mainStringRendering,
            );

            $rubricRef->setHref(
                DIRECTORY_SEPARATOR . $this->buildPathFor(static::RUBRIC_BLOCKS_FOLDER_NAME, $rubricNewHref),
            );
        }

        $this->auditPlatformLogger->info(
            sprintf('[%s] Rubric blocks processed successfully', $compilationId),
        );
    }

    /**
     * @throws XmlStorageException
     */
    private function createRubricBlockXML(RubricBlock $rubricBlock): string
    {
        $doc = new XmlDocument();

        $doc->setDocumentComponent($rubricBlock);

        return $doc->saveToString();
    }

    /**
     * @throws CompilationException
     */
    private function processStyleSheets(
        RubricBlock $rubricBlock,
        string $compilationId,
        string $extractedQtiTestFolderRelativePath,
        ?string $localePath = null,
    ): void {
        /** @var StylesheetCollection $rubricStylesheets */
        $rubricStylesheets = $rubricBlock->getStylesheets();

        /** @var Stylesheet $stylesheet */
        foreach ($rubricStylesheets as $stylesheet) {
            $newUrl = $this->buildPathFor(
                $compilationId,
                $localePath,
                self::RUBRIC_BLOCKS_FOLDER_NAME,
                $stylesheet->getHref(),
            );

            $this->assetDownloader->download(
                $this->buildPathFor(
                    $compilationId,
                    $localePath,
                    $extractedQtiTestFolderRelativePath,
                ),
                dirname($newUrl),
                $stylesheet->getHref(),
            );

            $stylesheet->setHref(sprintf("{{ baseUrl }}{{ signAssetUrl('%s') }}", DIRECTORY_SEPARATOR . $newUrl));
        }

        $stylesheets = new StylesheetCollection();

        $stylesheets->merge($rubricStylesheets);
        $rubricBlock->setStylesheets($stylesheets);
    }

    /**
     * @throws CompilationException
     */
    private function copyRemoteResources(
        QtiComponent $rubricBlock,
        string $extractedQtiTestFolderRelativePath,
        string $compilationId,
        ?string $localePath = null,
    ): void {
        $search = $rubricBlock->getComponentsByClassName(['object', 'img']);

        foreach ($search as $component) {
            $assetData = $this->getAssetData($component);

            if (isset($assetData)) {
                $newUrl = $this->buildPathFor(
                    $compilationId,
                    $localePath,
                    self::RUBRIC_BLOCKS_FOLDER_NAME,
                    $assetData,
                );

                $this->assetDownloader->download(
                    $this->buildPathFor(
                        $compilationId,
                        $localePath,
                        $extractedQtiTestFolderRelativePath,
                    ),
                    dirname($newUrl),
                    $assetData,
                );

                $this->setAssetNewUrl(
                    $component,
                    sprintf("{{ baseUrl }}{{ signAssetUrl('%s') }}", DIRECTORY_SEPARATOR . $newUrl),
                );
            }
        }
    }

    private function setAssetNewUrl(QtiComponent $component, string $newUrl): void
    {
        switch ($component->getQtiClassName()) {
            case 'object':
                /** @var ObjectElement $component */
                $component->setData($newUrl);
                break;

            case 'img':
                /** @var Img $component */
                $component->setSrc($newUrl);
                break;
        }
    }

    private function getAssetData(QtiComponent $component): ?string
    {
        switch ($component->getQtiClassName()) {
            case 'object':
                /** @var ObjectElement $component */
                return $component->getData();

            case 'img':
                /** @var Img $component */
                return $component->getSrc();
        }

        return null;
    }
}
