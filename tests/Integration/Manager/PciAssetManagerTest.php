<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Manager;

use App\Manager\PciAssetManager;
use App\Tests\Traits\LoggerTestingTrait;
use App\Traits\FilesystemTrait;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemReader;
use League\Flysystem\FilesystemWriter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Monolog\Logger;
use OAT\Library\QtiItemJsonCompilation\Asset\AssetDownloader;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PciAssetManagerTest extends KernelTestCase
{
    use FilesystemTrait;
    use LoggerTestingTrait;

    private PciAssetManager $subject;

    private FilesystemWriter $qtiPackageExtractorStorage;

    private FilesystemReader $qtiAssetManager;

    private Filesystem $testLocalStorage;

    public function setUp(): void
    {
        self::bootKernel();

        $this->qtiPackageExtractorStorage = static::getContainer()->get('qti_package_extractor.storage');
        $this->qtiAssetManager = static::getContainer()->get('qti_asset_manager.storage');
        $this->testLocalStorage = new Filesystem(new LocalFilesystemAdapter('tests/Resources/Qti/ExtractedPackages'));

        $this->setUpTestLogHandler();

        $this->subject = new PciAssetManager(
            static::getContainer()->get(AssetDownloader::class),
            static::getContainer()->get(LoggerInterface::class),
        );
    }

    public function tearDown(): void
    {
        $this->qtiPackageExtractorStorage->deleteDirectory('compilationId');
    }

    public function testCompilesPciItems(): void
    {
        $this->expectResources('PCI');

        $portableElements = $this->subject->compileAssets(
            $this->createPortableElements(),
            'compilationId/Q01',
            'compilationId',
        );

        $this->assertRuntimeAssetsExist($portableElements['pci']['likertScaleInteraction'][0]['runtime']);
    }

    public function testCompilesImsPciItems(): void
    {
        $this->expectResources('IMSPCI');

        $portableElements = [
            'pci' => [
                'likertCompactInteraction' => [
                    [
                        'version' => '1.2.0',
                        'typeIdentifier' => 'likertCompactInteraction',
                        'runtime' => [
                            'hook' => '../../likert/runtime/js/likertInteraction.min.js',
                        ],
                    ],
                ],
                'fractionModelInteraction' => [
                    [
                        'version' => '1.0.0',
                        'typeIdentifier' => 'fractionModelInteraction',
                        'runtime' => [
                            'hook' => '../../runtime/fractionModelInteraction.min.js',
                        ],
                    ],
                ],
                'graphFunctionInteraction' => [
                    [
                        'version' => '1.0.0',
                        'typeIdentifier' => 'graphFunctionInteraction',
                        'runtime' => [
                            'hook' => '../../runtime/graphFunctionInteraction.min.js',
                        ],
                    ],
                ],
                'graphLineAndPointInteraction' => [
                    [
                        'version' => '1.0.0',
                        'typeIdentifier' => 'graphLineAndPointInteraction',
                        'runtime' => [
                            'hook' => '../../runtime/graphLineAndPointInteraction.min.js',
                        ],
                    ],
                ],
                'graphNumberLineInteraction' => [
                    [
                        'version' => '1.0.0',
                        'typeIdentifier' => 'graphNumberLineInteraction',
                        'runtime' => [
                            'hook' => '../../runtime/graphNumberLineInteraction.min.js',
                        ],
                    ],
                ],
                'graphPointLineGraphInteraction' => [
                    [
                        'version' => '1.0.0',
                        'typeIdentifier' => 'graphPointLineGraphInteraction',
                        'runtime' => [
                            'hook' => '../../runtime/graphPointLineGraphInteraction.min.js',
                        ],
                    ],
                ],
                'graphZoomNumberLineInteraction' => [
                    [
                        'version' => '1.0.0',
                        'typeIdentifier' => 'graphZoomNumberLineInteraction',
                        'runtime' => [
                            'hook' => '../../runtime/graphZoomNumberLineInteraction.min.js',
                        ],
                    ],
                ],
                'likertInteraction' => [
                    [
                        'version' => '1.2.0',
                        'typeIdentifier' => 'likertInteraction',
                        'runtime' => [
                            'hook' => '../../likert/runtime/js/likertInteraction.js',
                        ],
                    ],
                ],
            ],
            'pic' => [],
        ];

        $portableElements = $this->subject->compileAssets(
            $portableElements,
            'compilationId/items/i5fb260920ffba14b64692994a183a26',
            'compilationId',
        );

        foreach ($portableElements['pci'] as $elements) {
            foreach ($elements as $element) {
                $this->assertRuntimeAssetsExist($element['runtime']);
            }
        }
    }

    public function testCompilesPciWithDifferentVersionInDifferentFolders(): void
    {
        $this->expectResources('IMSPCI');

        $portableElements = [
            'pci' => [
                'no version' => [
                    [
                        'typeIdentifier' => 'likertCompactInteraction',
                        'runtime' => [
                            'hook' => '../../likert/runtime/js/likertInteraction.min.js',
                        ],
                    ],
                ],
                'old version' => [
                    [
                        'version' => '1.2.0',
                        'typeIdentifier' => 'likertCompactInteraction',
                        'runtime' => [
                            'hook' => '../../likert/runtime/js/likertInteraction.min.js',
                        ],
                    ],
                ],
                'new version' => [
                    [
                        'version' => '1.2.1',
                        'typeIdentifier' => 'likertCompactInteraction',
                        'runtime' => [
                            'hook' => '../../likert/runtime/js/likertInteraction.min.js',
                        ],
                    ],
                ],
            ],
        ];

        $portableElements = $this->subject->compileAssets(
            $portableElements,
            'compilationId/items/i5fb260920ffba14b64692994a183a26',
            'compilationId',
        );

        $hooks = [
            $portableElements['pci']['no version'][0]['runtime']['hook'],
            $portableElements['pci']['old version'][0]['runtime']['hook'],
            $portableElements['pci']['new version'][0]['runtime']['hook'],
        ];

        $this->assertCount(3, $portableElements['pci']);
        $this->assertCount(3, array_unique($hooks));

        foreach ($portableElements['pci'] as $elements) {
            foreach ($elements as $element) {
                $this->assertRuntimeAssetsExist($element['runtime']);
            }
        }
    }

    public function testReturnsOriginalPortableElementsWhenPciKeyIsEmpty(): void
    {
        $expectedPortableElements = ['pci' => [], 'pic' => []];

        $portableElements = $this->subject->compileAssets(
            $expectedPortableElements,
            'compilationId/Q01',
            'compilationId',
        );

        $this->assertSame($expectedPortableElements, $portableElements);
    }

    public function testThrowsWhenPciElementDoesNotHaveHookKeyDefinedInRuntimeSection(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mandatory hook resource is missing in portable elements');

        $this->subject->compileAssets(
            $this->createPortableElements(false),
            'compilationId/Q01',
            'compilationId',
        );
    }

    private function assertRuntimeAssetsExist(array $runtimeAssets): void
    {
        foreach ($runtimeAssets as $assets) {
            if (!is_array($assets)) {
                $this->assertAssetExists($assets);

                continue;
            }

            foreach ($assets as $asset) {
                $this->assertAssetExists($asset);
            }
        }
    }

    private function assertAssetExists(string $asset): void
    {
        $this->assertTrue(
            $this->qtiAssetManager->has($asset),
            'Could not find the expected PCI asset ' . $asset,
        );

        $this->assertHasLogRecordWithMessage(
            sprintf('[compilationId] PCI resource %s has been moved to the storage', $asset),
            Logger::INFO,
        );
    }

    private function expectResources(string $source): void
    {
        /** @var string[] $assets */
        $assets = [];

        foreach ($this->testLocalStorage->listContents($source, true) as $object) {
            if ($object['type'] === 'file') {
                $assets[] = substr($object['path'], strlen($source) + 1);
            }
        }

        foreach ($assets as $asset) {
            $content = $this->testLocalStorage->read($this->buildPathFor($source, $asset));

            $this->qtiPackageExtractorStorage->write(
                $this->buildPathFor('compilationId', $asset),
                $content,
            );
        }
    }

    private function createPortableElements(bool $valid = true): array
    {
        $data = [
            'pci' => [
                'likertScaleInteraction' => [
                    [
                        'version' => '0.5.0',
                        'typeIdentifier' => 'likertScaleInteraction',
                        'runtime' => [
                            'stylesheets' => [
                                'likertScaleInteraction/runtime/css/base.css',
                                'likertScaleInteraction/runtime/css/likertScaleInteraction.css',
                            ],
                            'mediaFiles' => [
                                'likertScaleInteraction/runtime/assets/ThumbDown.png',
                                'likertScaleInteraction/runtime/assets/ThumbUp.png',
                                'likertScaleInteraction/runtime/css/img/bg.png',
                            ],
                            'src' => [],
                        ],
                    ],
                ],
            ],
            'pic' => [],
        ];

        if ($valid) {
            $data['pci']['likertScaleInteraction'][0]['runtime']['hook'] = 'likertScaleInteraction/runtime/likertScaleInteraction.min.js';
        }

        return $data;
    }
}
