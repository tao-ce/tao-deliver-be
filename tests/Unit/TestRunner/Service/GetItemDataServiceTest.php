<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\Service;

use App\Cache\CacheTrait;
use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Qti\Compiler\QtiPackageCompiler;
use App\Registry\SignedUrlGeneratorRegistry;
use App\Repository\DeliveryRepository;
use App\TestRunner\Service\GetItemDataService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use App\Traits\FilesystemTrait;
use Carbon\Carbon;
use League\Flysystem\FilesystemReader;
use League\Flysystem\UnableToReadFile;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Cache\CacheInterface;

class GetItemDataServiceTest extends KernelTestCase
{
    use DomainTestingTrait;
    use FilesystemTrait;
    use QtiTestingTrait;
    use LoggerTestingTrait;
    use CacheTrait;

    protected CacheInterface $cache;
    private SignedUrlGeneratorRegistry $signedUrlGeneratorRegistry;
    private DeliveryExecution $deliveryExecution;
    private FilesystemReader $storage;
    private LoggerInterface $logger;
    private LoggerInterface $auditLogger;

    public function setUp(): void
    {
        Carbon::setTestNow(Carbon::create(2000, 1, 1, 0, 0, 0, 'Europe/Luxembourg'));

        static::bootKernel();

        $this->setUpTestLogHandler();

        $this->cache = $this->createMock(CacheInterface::class);
        $this->storage = $this->createMock(FilesystemReader::class);
        $this->logger = static::getContainer()->get(LoggerInterface::class);
        $this->auditLogger = static::getContainer()->get('monolog.logger.audit_delivery_execution');

        $this->signedUrlGeneratorRegistry = static::getContainer()->get(SignedUrlGeneratorRegistry::class);

        $this->copyCompiledTestToStorage(['compact-test.xml', 'Item-Q01/item.json'], 'BasicAssets');
        $this->copyCompiledTestToStorage(
            ['compact-test.xml', 'Item-Q02/item.json', 'Item-Q02/portableElements.json'],
            'BasicAssets',
        );
        $this->copyCompiledTestToStorage(['Item-Q04/item.json', 'Item-Q04/portableElements.json'], 'BasicAssets');

        $this->deliveryExecution = $this->getDeliveryExecution();
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testItWillFetchItemDataFromCache(): void
    {
        $this->cache
            ->expects($this->exactly(1))
            ->method('get')
            ->withConsecutive(
                [
                    $this->getCacheKey(
                        'BasicAssets',
                        'Item-Q02',
                        QtiPackageCompiler::JSON_ITEM_FILE_NAME,
                    ),
                ],
                [
                    $this->getCacheKey(
                        'BasicAssets',
                        'Item-Q02',
                        QtiPackageCompiler::JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME,
                    ),
                ],
            )
            ->willReturnOnConsecutiveCalls(
                ['assets' => [['assets']]],
                ['pci' => [], 'pic' => []],
            );

        $subject = $this->getSubject($this->cache);

        $subject->getItemDataByDeliveryExecution('Item-Q02', $this->deliveryExecution);

        $this->assertHasLogRecordWithMessage(
            '[userId#BasicAssets#resultId#tenantId][GetItemDataService] - got item data Item-Q02 from the cache',
            Logger::DEBUG,
            'audit_delivery_execution',
        );
    }

    public function testItWillFetchFromStorageAndStoreItInCache(): void
    {
        $jsonItemCacheKey = $this->getCacheKey(
            'BasicAssets',
            'Item-Q02',
            QtiPackageCompiler::JSON_ITEM_FILE_NAME,
        );
        $jsonItemPortableElementsCacheKey = $this->getCacheKey(
            'BasicAssets',
            'Item-Q02',
            QtiPackageCompiler::JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME,
        );

        $this->cache
            ->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive(
                [$jsonItemCacheKey],
                [$jsonItemCacheKey],
                [$jsonItemPortableElementsCacheKey],
                [$jsonItemPortableElementsCacheKey],
            )
            ->willReturn(null);

        $this->storage
            ->expects($this->once())
            ->method('read')
            ->with('BasicAssets/Item-Q02/item.json')
            ->willReturn('{"data": "item data"}');

        $subject = $this->getSubject($this->cache, storage: $this->storage);

        $subject->getItemDataByDeliveryExecution('Item-Q02', $this->deliveryExecution);
    }

    public function testItWorksProperlyEvenIfGetInCacheIsNotWorkingAndLogError(): void
    {
        $jsonItemCacheKey = $this->getCacheKey(
            'BasicAssets',
            'Item-Q02',
            QtiPackageCompiler::JSON_ITEM_FILE_NAME,
        );
        $jsonItemPortableElementsCacheKey = $this->getCacheKey(
            'BasicAssets',
            'Item-Q02',
            QtiPackageCompiler::JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME,
        );

        $this->cache
            ->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive(
                [$jsonItemCacheKey],
                [$jsonItemCacheKey],
                [$jsonItemPortableElementsCacheKey],
                [$jsonItemPortableElementsCacheKey],
            )
            ->willThrowException(new CacheException('cache storage unavailable'));

        $this->storage
            ->expects($this->once())
            ->method('read')
            ->with('BasicAssets/Item-Q02/item.json')
            ->willReturn('{"data": "item data"}');

        $subject = $this->getSubject($this->cache, storage: $this->storage);

        $subject->getItemDataByDeliveryExecution('Item-Q02', $this->deliveryExecution);

        $this->assertHasLogRecord(['message' => 'cache storage unavailable',], Logger::ERROR);
    }

    public function testItWorksProperlyEvenIfSetInCacheIsNotWorkingAndLogError(): void
    {
        $jsonItemCacheKey = $this->getCacheKey(
            'BasicAssets',
            'Item-Q02',
            QtiPackageCompiler::JSON_ITEM_FILE_NAME,
        );
        $jsonItemPortableElementsCacheKey = $this->getCacheKey(
            'BasicAssets',
            'Item-Q02',
            QtiPackageCompiler::JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME,
        );

        $executionNumber = 0;
        $this->cache
            ->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive(
                [$jsonItemCacheKey],
                [$jsonItemCacheKey],
                [$jsonItemPortableElementsCacheKey],
                [$jsonItemPortableElementsCacheKey],
            )
            ->willReturnCallback(static function () use (&$executionNumber) {
                $executionNumber++;
                if ($executionNumber === 1) {
                    return null;
                }

                if ($executionNumber === 2) {
                    throw new CacheException('cache storage unavailable');
                }
            });

        $this->storage
            ->expects($this->once())
            ->method('read')
            ->with('BasicAssets/Item-Q02/item.json')
            ->willReturn('{"data": "item data"}');

        $subject = $this->getSubject($this->cache, storage: $this->storage);

        $subject->getItemDataByDeliveryExecution('Item-Q02', $this->deliveryExecution);

        $this->assertHasLogRecord(['message' => 'cache storage unavailable',], Logger::ERROR);
    }

    public function testItWillThrownAnExceptionIfErrorComingFromStorage(): void
    {
        $this->cache
            ->expects($this->exactly(1))
            ->method('get')
            ->with(
                $this->getCacheKey(
                    'BasicAssets',
                    'Item-Q02',
                    QtiPackageCompiler::JSON_ITEM_FILE_NAME,
                ),
            )
            ->willReturn(null);
        $storage = $this->createMock(FilesystemReader::class);
        $storage
            ->expects($this->exactly(1))
            ->method('read')
            ->willThrowException(new UnableToReadFile('File not found'));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('File not found');

        $subject = $this->getSubject($this->cache, $storage);

        $subject->getItemDataByDeliveryExecution('Item-Q02', $this->deliveryExecution);
    }

    public function testItHandlesReviewModeWithoutAnswers(): void
    {
        $subject = $this->getSubject();

        $this->storage
            ->expects($this->exactly(4))
            ->method('read')
            ->with('BasicAssets/Item-Q02/item.json')
            ->willReturn('{"data": "item data"}');

        $deliveryExecution = $this->getDeliveryExecution(
            'dummyItemState',
            [
                'custom' => [
                    LtiCustomSettings::PARAM_REVIEW_MODE => true,
                ],
                'is_anonymous' => true,
            ],
        );

        $response = $subject->getItemDataByDeliveryExecution(
            'Item-Q02',
            $deliveryExecution,
        );

        $this->assertEquals(['data' => 'item data'], $response);
        $this->assertEquals(
            $response,
            $subject->getItemDataByDelivery(
                'Item-Q02',
                $this->createTestDelivery($deliveryExecution->getDeliveryId()),
                null,
            ),
        );
        $this->assertEquals(
            $response,
            $subject->getItemDataByDelivery(
                'Item-Q02',
                $this->createTestDelivery($deliveryExecution->getDeliveryId(), mainLocale: 'en-US'),
                null,
            ),
        );
        $this->assertEquals(
            $response,
            $subject->getItemDataByDelivery(
                'Item-Q02',
                $this->createTestDelivery($deliveryExecution->getDeliveryId(), mainLocale: 'en-US'),
                'en-US',
            ),
        );
    }

    public function testGetItemDataByDeliveryExecutionWithLocale(): void
    {
        $subject = $this->getSubject();

        $deliveryExecution = $this->getDeliveryExecution(locale: 'en-US');

        $cacheKey = $this->getCacheKey(
            'BasicAssets',
            'Item-Q02',
            QtiPackageCompiler::JSON_ITEM_FILE_NAME,
            'en-US',
        );

        $this->cache
            ->expects($this->exactly(4))
            ->method('get')
            ->with($cacheKey)
            ->willReturn(null);

        $itemDataPath = $this->buildPathFor(
            'BasicAssets',
            Delivery::LOCALE_FOLDER_NAME,
            'en-US',
            'Item-Q02/item.json',
        );

        $this->storage
            ->expects($this->exactly(2))
            ->method('read')
            ->with($itemDataPath)
            ->willReturn('{"data": "item data"}');

        $response = $subject->getItemDataByDeliveryExecution('Item-Q02', $deliveryExecution);

        $this->assertEquals(['data' => 'item data'], $response);
        $this->assertEquals(
            $response,
            $subject->getItemDataByDelivery(
                'Item-Q02',
                $this->createTestDelivery($deliveryExecution->getDeliveryId()),
                'en-US',
            ),
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#BasicAssets#resultId#tenantId][GetItemDataService] - got item data Item-Q02 from the compiled delivery storage',
            Logger::DEBUG,
            'audit_delivery_execution',
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#BasicAssets#resultId#tenantId][GetItemDataService] - put item data Item-Q02 in the cache',
            Logger::DEBUG,
            'audit_delivery_execution',
        );
    }

    public function testGetItemDataByDeliveryExecutionWithoutLocale(): void
    {
        $subject = $this->getSubject();

        $deliveryExecution = $this->getDeliveryExecution();

        $cacheKey = $this->getCacheKey(
            'BasicAssets',
            'Item-Q02',
            QtiPackageCompiler::JSON_ITEM_FILE_NAME,
        );

        $this->cache
            ->expects($this->exactly(2))
            ->method('get')
            ->with($cacheKey)
            ->willReturn(null);

        $itemDataPath = 'BasicAssets/Item-Q02/item.json';

        $this->storage
            ->expects($this->once())
            ->method('read')
            ->with($itemDataPath)
            ->willReturn('{"data": "item data"}');

        $response = $subject->getItemDataByDeliveryExecution('Item-Q02', $deliveryExecution);



        $this->assertHasLogRecordWithMessage(
            '[userId#BasicAssets#resultId#tenantId][GetItemDataService] - got item data Item-Q02 from the compiled delivery storage',
            Logger::DEBUG,
            'audit_delivery_execution',
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#BasicAssets#resultId#tenantId][GetItemDataService] - put item data Item-Q02 in the cache',
            Logger::DEBUG,
            'audit_delivery_execution',
        );
    }

    private function assertDummyItemResponse(array $response): void
    {
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('assets', $response);
        $this->assertArrayHasKey('img', $response['assets']);
        $this->assertArrayHasKey('logo.png', $response['assets']['img']);
        $this->assertArrayHasKey('type', $response);
    }

    private function getDeliveryExecution(
        string $itemState = 'dummyItemState',
        array $ltiParameters = ['ltiLaunchParameters'],
        ?string $locale = null,
    ): DeliveryExecution {
        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#BasicAssets#resultId#tenantId',
            'BasicAssets',
            'tenantId',
            $ltiParameters,
            locale: $locale,
        );

        $deliveryExecution->addItemState('Item-Q02', $itemState);

        return $deliveryExecution;
    }

    private function getSubject(
        ?CacheInterface $cache = null,
        ?FilesystemReader $storage = null,
        ?LoggerInterface $logger = null,
        ?LoggerInterface $auditLogger = null,
    ): GetItemDataService {
        return new GetItemDataService(
            $storage ?? $this->storage,
            $cache ?? $this->cache,
            $logger ?? $this->logger,
            $auditLogger ?? $this->auditLogger,
        );
    }
}
