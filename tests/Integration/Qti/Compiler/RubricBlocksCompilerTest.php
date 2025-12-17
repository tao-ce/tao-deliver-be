<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Qti\Compiler;

use App\Generator\UuidGenerator;
use App\Qti\Compiler\RubricBlocksCompiler;
use App\Qti\Render\XhtmlRenderingEngine;
use App\Traits\FilesystemTrait;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use OAT\Library\QtiItemJsonCompilation\Asset\AssetDownloader;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use qtism\data\storage\xml\XmlCompactDocument;
use qtism\data\storage\xml\XmlDocument;
use qtism\runtime\rendering\markup\MarkupPostRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;

class RubricBlocksCompilerTest extends KernelTestCase
{
    use FilesystemTrait;

    /** @var FilesystemOperator */
    private $qtiCompiledDeliveriesStorage;

    /** @var LoggerInterface */
    private $logger;

    /** @var  XhtmlRenderingEngine */
    private $renderingEngine;

    /** @var MarkupPostRenderer */
    private $markupPostRenderer;

    /** @var UuidGenerator|MockObject */
    private $generator;

    /** @var RubricBlocksCompiler */
    private $subject;

    /** @var Filesystem */
    private $qtiPackageExtractorStorage;

    public function setUp(): void
    {
        self::bootKernel();

        $this->qtiCompiledDeliveriesStorage = static::getContainer()->get('qti_compiled_deliveries.storage');
        $this->logger = static::getContainer()->get(LoggerInterface::class);
        $this->renderingEngine = static::getContainer()->get(XhtmlRenderingEngine::class);
        $this->markupPostRenderer = static::getContainer()->get(MarkupPostRenderer::class);
        $this->generator = $this->createMock(UuidGenerator::class);
        $this->qtiPackageExtractorStorage = static::getContainer()->get('qti_package_extractor.storage');

        $this->subject = new RubricBlocksCompiler(
            $this->qtiCompiledDeliveriesStorage,
            $this->logger,
            $this->renderingEngine,
            $this->markupPostRenderer,
            $this->generator,
            static::getContainer()->get(AssetDownloader::class),
        );
    }

    public function tearDown(): void
    {
        $this->qtiPackageExtractorStorage->deleteDirectory('deliveryIdHash');
    }

    public function testCompileRubricBlocks(): void
    {
        $extractedQtiTestPath = __DIR__ . '/../../../Resources/Qti/ExtractedPackages/RubricBlocks/test.xml';
        $compactTestDocument = $this->createCompactTestFromQtiXml($extractedQtiTestPath);
        $compilationId = 'deliveryIdHash';
        $extractedQtiTestRelativePath = 'RubricBlocks';

        $this->prepareAssets($this->buildPathFor($compilationId, $extractedQtiTestRelativePath));

        $this->subject->compileRubricBlocks($compactTestDocument, $compilationId, $extractedQtiTestRelativePath);

        $itHasFiles = $this->qtiCompiledDeliveriesStorage->has(
            $this->buildPathFor($compilationId, RubricBlocksCompiler::RUBRIC_BLOCKS_FOLDER_NAME),
        );

        $this->assertTrue($itHasFiles);

        $assessmentTest = $compactTestDocument->getDocumentComponent();
        $rubricBlockRefs = $assessmentTest->getComponentsByClassName('rubricBlockRef');

        foreach ($rubricBlockRefs as $rubricRef) {
            $this->assertEquals('/rubricBlocks/rubricBlock_RB_S01_1.html.twig', $rubricRef->getHref());
            $rubricBlockFile = $this->qtiCompiledDeliveriesStorage->read(
                $this->buildPathFor(
                    $compilationId,
                    $rubricRef->getHref(),
                ),
            );
            $compiledRubricBlock = file_get_contents(
                'tests/Resources/Qti/CompiledPackages/RubricBlocks/rubricBlocks/rubricBlock_RB_S01_1.html.twig',
            );

            $this->assertEquals($compiledRubricBlock, $rubricBlockFile);
        }
    }

    public function testCompileRubricBlocksWithPrintedVariable(): void
    {
        $extractedQtiTestPath = __DIR__ . '/../../../Resources/Qti/ExtractedPackages/RubricBlocksWithPrintedVariables/test.xml';
        $compactTestDocument = $this->createCompactTestFromQtiXml($extractedQtiTestPath);
        $compilationId = 'deliveryIdHash';

        $this->subject->compileRubricBlocks($compactTestDocument, $compilationId, 'RubricBlocksWithPrintedVariables');

        $assessmentTest = $compactTestDocument->getDocumentComponent();
        $rubricBlockRefs = $assessmentTest->getComponentsByClassName('rubricBlockRef');
        $rubricRef = $rubricBlockRefs[0];

        $this->assertEquals('/rubricBlocks/rubricBlock_RB_S02_1.html.twig', $rubricRef->getHref());

        $rubricBlockFile = $this->qtiCompiledDeliveriesStorage->read(
            $this->buildPathFor(
                $compilationId,
                $rubricRef->getHref(),
            ),
        );

        $expectedCompiledRubricBlock = file_get_contents(
            __DIR__ . '/../../../Resources/Qti/CompiledPackages/RubricBlocksWithPrintedVariables/rubricBlocks/compiledRubricBlock.html.twig',
        );

        $this->assertEquals($expectedCompiledRubricBlock, $rubricBlockFile);
    }

    public function testCompileRubricBlocksWithException(): void
    {
        $extractedQtiTestPath = __DIR__ . '/../../../Resources/Qti/ExtractedPackages/RubricBlocks/test.xml';
        $compactTestDocument = $this->createCompactTestFromQtiXml($extractedQtiTestPath);
        $this->expectException(Throwable::class);
        $this->subject->compileRubricBlocks(
            $compactTestDocument,
            'test',
            'extractedQtiTestRelativePath',
        );
    }

    private function createCompactTestFromQtiXml(string $filePath): XmlCompactDocument
    {
        $xmlDocument = new XmlDocument();

        $xmlDocument->load($filePath);

        return XmlCompactDocument::createFromXmlAssessmentTestDocument($xmlDocument);
    }

    private function prepareAssets($path): void
    {
        $assets = [
            'stylesheets/dogfight.css',
            'img/dogfight.png',
        ];

        foreach ($assets as $asset) {
            $content = file_get_contents('tests/Resources/Qti/ExtractedPackages/RubricBlocks/' . $asset);
            $this->qtiPackageExtractorStorage->write(
                $this->buildPathFor($path, $asset),
                $content,
            );
        }
    }
}
