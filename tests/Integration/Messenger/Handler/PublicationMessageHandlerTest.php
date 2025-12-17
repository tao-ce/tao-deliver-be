<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Messenger\Handler;

use App\Domain\Delivery\Model\Delivery;
use App\Domain\Publication\Model\Publication;
use App\Messenger\Message\PublicationMessage;
use App\Qti\Compiler\QtiPackageCompiler;
use App\Tests\Traits\CacheTestingTrait;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use App\Traits\FilesystemTrait;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\FilesystemReader;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PublicationMessageHandlerTest extends KernelTestCase
{
    use LoggerTestingTrait;
    use DocumentTestingTrait;
    use MessengerTestingTrait;
    use DomainTestingTrait;
    use FilesystemTrait;
    use CacheTestingTrait;

    private FilesystemOperator $base64Storage;
    private FilesystemReader $compliedDeliveryStorage;

    public function setUp(): void
    {
        self::bootKernel();

        $this->setUpTestLogHandler();
        $this->setUpTestDocumentManager();
        $this->setUpTestMessageBus();
        $this->setUpTestCache();

        $this->base64Storage = static::getContainer()->get('base64_zip.storage');
        $this->compliedDeliveryStorage = static::getContainer()->get('qti_compiled_deliveries.storage');
    }

    /**
     * @dataProvider publicationSuccessDataProvider
     */
    public function testPublicationMessageHandlingSuccess(
        Publication $publication,
        string $base64FileName,
        array $expectedQtiItemsMapping,
        array $expectedItems,
        array $expectedSupportedLocales,
    ): void {
        $this->saveDocument($publication);

        if ($publication->getPackagePath()) {
            $this->base64Storage->write(
                $publication->getPackagePath(),
                file_get_contents(
                    $this->buildPathFor(__DIR__, '../../../Resources/Qti/Base64EncodedPackages', $base64FileName),
                ),
            );
        }

        $message = new PublicationMessage(
            $publication->getId(),
            $publication->getTenantId(),
            $publication->getPackagePath(),
            $publication->getPackageRef(),
            $publication->getPackageConfiguration(),
        );

        $this->publishMessage($message);

        $this->assertCountTransportMessages('publication', 1);

        $this->consumeTransportMessages('publication', noReset: 1);

        $this->assertHasDocumentWithId(Publication::class, $publication->getId());

        /** @var Publication $expectedPublication */
        $expectedPublication = $this->findDocumentById(Publication::class, $publication->getId());
        $this->assertEquals(Publication::STATUS_SUCCESS, $expectedPublication->getStatus());

        foreach ($expectedPublication->getReports() as $report) {
            $this->assertEquals('success', $report['type']);
            $this->assertHasLogRecordWithMessage($report['message'], Logger::INFO, 'audit_platform');
        }

        $this->assertFalse($this->base64Storage->has($publication->getPackagePath()));

        $this->assertHasDocumentWithId(Delivery::class, $expectedPublication->getDeliveryId());

        /** @var Delivery $expectedDelivery */
        $expectedDelivery = $this->findDocumentById(Delivery::class, $expectedPublication->getDeliveryId());
        $this->assertTrue(
            $this->compliedDeliveryStorage->has(
                $this->buildPathFor($expectedDelivery->getId(), QtiPackageCompiler::COMPACT_TEST_FILE_NAME),
            ),
        );

        $this->assertEquals($expectedDelivery->getQtiItemsMapping(), $expectedQtiItemsMapping);

        $this->assertEquals(
            $expectedSupportedLocales,
            $expectedDelivery->getSupportedLocales(),
            'Delivery supported locales do not match expected value',
        );

        $this->assertCacheKeyExist(
            $this->getCacheKey(
                $expectedPublication->getDeliveryId(),
                null,
                QtiPackageCompiler::COMPACT_TEST_FILE_NAME,
            ),
        );

        foreach ($expectedItems as $expectedItem) {
            $this->assertCacheKeyExist(
                $this->getCacheKey(
                    $expectedPublication->getDeliveryId(),
                    $expectedItem,
                    QtiPackageCompiler::JSON_ITEM_FILE_NAME,
                ),
            );

            $this->assertCacheKeyExist(
                $this->getCacheKey(
                    $expectedPublication->getDeliveryId(),
                    $expectedItem,
                    QtiPackageCompiler::JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME,
                ),
            );
        }
    }


    /**
     * @dataProvider publicationLongTitleFailureDataProvider
     */
    public function testPublicationLongTitleMessageHandlingFailure(
        Publication $publication,
        string $base64FileName,
        array $expectedQtiItemsMapping,
        array $expectedItems,
    ): void {
        $this->saveDocument($publication);

        if ($publication->getPackagePath()) {
            $this->base64Storage->write(
                $publication->getPackagePath(),
                file_get_contents(
                    $this->buildPathFor(
                        __DIR__,
                        '../../../Resources/Qti/Base64EncodedPackages',
                        $base64FileName,
                    ),
                ),
            );
        }

        $message = new PublicationMessage(
            $publication->getId(),
            $publication->getTenantId(),
            $publication->getPackagePath(),
            $publication->getPackageRef(),
            $publication->getPackageConfiguration(),
        );

        $this->publishMessage($message);

        $this->assertCountTransportMessages('publication', 1);

        $this->consumeTransportMessages('publication');

        $this->assertHasDocumentWithId(Publication::class, $publication->getId());

        /** @var Publication $expectedPublication */
        $expectedPublication = $this->findDocumentById(Publication::class, $publication->getId());
        $this->assertEquals(Publication::STATUS_FAILED, $expectedPublication->getStatus());
    }

    public function testPublicationWithPatternValidation(): void
    {
        $publication = $this->createTestPublication(Publication::STATUS_CREATED, 'id', 'tenantId', 'filePath', '', []);
        $this->saveDocument($publication);

        $this->base64Storage->write(
            $publication->getPackagePath(),
            file_get_contents(
                $this->buildPathFor(
                    __DIR__,
                    '../../../Resources/Qti/Base64EncodedPackages/pattern_validation.txt',
                ),
            ),
        );

        $message = new PublicationMessage(
            $publication->getId(),
            $publication->getTenantId(),
            $publication->getPackagePath(),
            $publication->getPackageRef(),
            $publication->getPackageConfiguration(),
        );

        $this->publishMessage($message);
        $this->consumeTransportMessages('publication');

        /** @var Publication $expectedPublication */
        $expectedPublication = $this->findDocumentById(Publication::class, $publication->getId());

        self::assertStringContainsString(
            '"patternMask":"[a-b]*"',
            $this->compliedDeliveryStorage->read(
                $this->buildPathFor($expectedPublication->getDeliveryId(), 'item-6', 'item.json'),
            ),
        );
    }

    public function testDeliveryPublicationMessageHandlingQtiCompilationFailure(): void
    {
        $publication = $this->createTestPublication(Publication::STATUS_CREATED);
        $this->saveDocument($publication);

        $this->base64Storage->write(
            $publication->getPackagePath(),
            file_get_contents(__DIR__ . '/../../../Resources/Qti/Base64EncodedPackages/invalid_package.txt'),
        );

        $message = new PublicationMessage(
            $publication->getId(),
            $publication->getTenantId(),
            $publication->getPackagePath(),
            $publication->getPackageRef(),
            $publication->getPackageConfiguration(),
        );

        $this->publishMessage($message);

        $this->assertCountTransportMessages('publication', 1);

        $this->consumeTransportMessages('publication');

        $this->assertHasDocumentWithId(Publication::class, $publication->getId());

        /** @var Publication $expectedPublication */
        $expectedPublication = $this->findDocumentById(Publication::class, $publication->getId());
        $this->assertEquals(Publication::STATUS_FAILED, $expectedPublication->getStatus());

        foreach ($expectedPublication->getReports() as $report) {
            $this->assertEquals('error', $report['type']);
            $this->assertHasLogRecordWithMessage($report['message'], Logger::ERROR);
        }
    }

    public function testDeliveryPublicationMessageHandlingQtiCompilationFailureOnMissingItems(): void
    {
        $publication = $this->createTestPublication(Publication::STATUS_CREATED);
        $this->saveDocument($publication);

        $this->base64Storage->write(
            $publication->getPackagePath(),
            file_get_contents(__DIR__ . '/../../../Resources/Qti/Base64EncodedPackages/missing_items_package.txt'),
        );

        $message = new PublicationMessage(
            $publication->getId(),
            $publication->getTenantId(),
            $publication->getPackagePath(),
            $publication->getPackageRef(),
            $publication->getPackageConfiguration(),
        );

        $this->publishMessage($message);

        $this->assertCountTransportMessages('publication', 1);

        $this->consumeTransportMessages('publication');

        $this->assertHasDocumentWithId(Publication::class, $publication->getId());

        /** @var Publication $expectedPublication */
        $expectedPublication = $this->findDocumentById(Publication::class, $publication->getId());
        $this->assertEquals(Publication::STATUS_FAILED, $expectedPublication->getStatus());

        foreach ($expectedPublication->getReports() as $report) {
            $this->assertStringContainsString('No items found in provided package', $report['message']);
            $this->assertEquals('error', $report['type']);
            $this->assertHasLogRecordWithMessage($report['message'], Logger::ERROR);
        }
    }

    public function testDeliveryPublicationMessageHandlingGenericFailure(): void
    {
        $publication = $this->createTestPublication(Publication::STATUS_CREATED);
        $this->saveDocument($publication);

        $message = new PublicationMessage(
            $publication->getId(),
            $publication->getTenantId(),
            $publication->getPackagePath(),
            $publication->getPackageRef(),
            $publication->getPackageConfiguration(),
        );

        $this->publishMessage($message);

        $this->assertCountTransportMessages('publication', 1);

        $this->consumeTransportMessages('publication');

        $this->assertHasDocumentWithId(Publication::class, $publication->getId());

        /** @var Publication $expectedPublication */
        $expectedPublication = $this->findDocumentById(Publication::class, $publication->getId());
        $this->assertEquals(Publication::STATUS_FAILED, $expectedPublication->getStatus());
    }

    public function publicationSuccessDataProvider(): array
    {
        return [
            'publication success with basic package' => [
                $this->createTestPublication(
                    Publication::STATUS_CREATED,
                    'id',
                    'tenantId',
                    'filePath',
                    '',
                    [],
                ),
                'basic_package.txt',
                [
                    'Item-Q01' => [
                        'itemIdentifier' => 'Q01',
                        'itemLabel' => null,
                        'itemTitle' => 'What is TAO?',
                    ],
                    'Item-Q02' => [
                        'itemIdentifier' => 'Q02',
                        'itemLabel' => null,
                        'itemTitle' => 'What are his favourite collectables?',
                    ],
                    'Item-Q03' => [
                        'itemIdentifier' => 'Q03',
                        'itemLabel' => null,
                        'itemTitle' => 'What does mean GT?',
                    ],
                ],
                [
                    'Item-Q01',
                    'Item-Q02',
                ],
                [],
            ],
            'publication success with basic with assessment items referencing same item' => [
                $this->createTestPublication(
                    Publication::STATUS_CREATED,
                    'id',
                    'tenantId',
                    'filePath',
                    '',
                    ['configuration' => ['myConfiguration']],
                ),
                'basic_with_assessment_items_referencing_same_item.txt',
                [
                    'Q01-1' => [
                        'itemIdentifier' => 'Q01',
                        'itemLabel' => 'Associate Things',
                        'itemTitle' => 'Associate Things',
                    ],
                    'Q01-2' => [
                        'itemIdentifier' => 'Q01',
                        'itemLabel' => 'Associate Things',
                        'itemTitle' => 'Associate Things',
                    ],
                ],
                [
                    'Q01-1',
                    'Q01-2',
                ],
                [],
            ],
            'publication success with hidden main locale' => [
                $this->createTestPublication(
                    Publication::STATUS_CREATED,
                    'id',
                    'tenantId',
                    'filePath',
                    '',
                    [
                        'metadata' => [
                            'test' => [
                                'hidden' => 'true',
                            ],
                        ],
                    ],
                    locale: 'en-US',
                ),
                'basic_package.txt',
                [
                    'Item-Q01' => [
                        'itemIdentifier' => 'Q01',
                        'itemLabel' => null,
                        'itemTitle' => 'What is TAO?',
                    ],
                    'Item-Q02' => [
                        'itemIdentifier' => 'Q02',
                        'itemLabel' => null,
                        'itemTitle' => 'What are his favourite collectables?',
                    ],
                    'Item-Q03' => [
                        'itemIdentifier' => 'Q03',
                        'itemLabel' => null,
                        'itemTitle' => 'What does mean GT?',
                    ],
                ],
                [
                    'Item-Q01',
                    'Item-Q02',
                ],
                [],
            ],
            'publication success with visible main locale' => [
                $this->createTestPublication(
                    Publication::STATUS_CREATED,
                    'id',
                    'tenantId',
                    'filePath',
                    '',
                    [
                        'metadata' => [
                            'test' => [
                                'hidden' => 'false',
                            ],
                        ],
                    ],
                    locale: 'en-US',
                ),
                'basic_package.txt',
                [
                    'Item-Q01' => [
                        'itemIdentifier' => 'Q01',
                        'itemLabel' => null,
                        'itemTitle' => 'What is TAO?',
                    ],
                    'Item-Q02' => [
                        'itemIdentifier' => 'Q02',
                        'itemLabel' => null,
                        'itemTitle' => 'What are his favourite collectables?',
                    ],
                    'Item-Q03' => [
                        'itemIdentifier' => 'Q03',
                        'itemLabel' => null,
                        'itemTitle' => 'What does mean GT?',
                    ],
                ],
                [
                    'Item-Q01',
                    'Item-Q02',
                ],
                ['en-US'],
            ],
            'publication success and visible main locale with missing configuration' => [
                $this->createTestPublication(
                    Publication::STATUS_CREATED,
                    'id',
                    'tenantId',
                    'filePath',
                    '',
                    [],
                    locale: 'en-US',
                ),
                'basic_package.txt',
                [
                    'Item-Q01' => [
                        'itemIdentifier' => 'Q01',
                        'itemLabel' => null,
                        'itemTitle' => 'What is TAO?',
                    ],
                    'Item-Q02' => [
                        'itemIdentifier' => 'Q02',
                        'itemLabel' => null,
                        'itemTitle' => 'What are his favourite collectables?',
                    ],
                    'Item-Q03' => [
                        'itemIdentifier' => 'Q03',
                        'itemLabel' => null,
                        'itemTitle' => 'What does mean GT?',
                    ],
                ],
                [
                    'Item-Q01',
                    'Item-Q02',
                ],
                ['en-US'],
            ],
        ];
    }

    public function publicationLongTitleFailureDataProvider(): array
    {
        return [
            'publication failure with basic package' => [
                $this->createTestPublication(Publication::STATUS_CREATED, 'id', 'tenantId', 'filePath', '', []),
                'long_title.txt',
                [
                    'Item-Q01' => [
                        'itemIdentifier' => 'Q01',
                        'itemLabel' => null,
                        'itemTitle' => 'What is TAO?',
                    ],
                ],
                [
                    'Item-Q01',
                ],
            ],
        ];
    }
}
