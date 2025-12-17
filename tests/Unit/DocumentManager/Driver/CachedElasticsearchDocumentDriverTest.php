<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Driver;

use App\DocumentManager\Driver\CachedElasticsearchDocumentDriver;
use ArrayObject;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\ElasticsearchDocumentManagerBundle\Driver\ElasticsearchDocumentDriver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CachedElasticsearchDocumentDriverTest extends TestCase
{
    private CachedElasticsearchDocumentDriver $subject;
    private ElasticsearchDocumentDriver|MockObject $driverMock;
    private LoggerInterface|MockObject $loggerMock;
    private TagAwareCacheInterface|MockObject $cacheMock;
    private array $cachableStorages = ['deliveries'];

    protected function setUp(): void
    {
        $this->driverMock = $this->createMock(ElasticsearchDocumentDriver::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->cacheMock = $this->createMock(TagAwareCacheInterface::class);

        $this->subject = new CachedElasticsearchDocumentDriver(
            $this->driverMock,
            $this->loggerMock,
            $this->cacheMock,
            $this->cachableStorages,
        );
    }

    public function testGetDocumentData(): void
    {
        $documentDriverDataMock = $this->createMock(DocumentDriverDataInterface::class);

        $this->driverMock
            ->expects($this->once())
            ->method('getDocumentData')
            ->with('foo', 'id')
            ->willReturn($documentDriverDataMock);

        $this->cacheMock
            ->expects($this->never())
            ->method('get');

        $this->assertSame($documentDriverDataMock, $this->subject->getDocumentData('foo', 'id'));
    }

    public function testGetDocumentDataWithCache(): void
    {
        $save = true;

        $documentDriverDataMock = $this->createMock(DocumentDriverDataInterface::class);

        $this->driverMock
            ->expects($this->once())
            ->method('getDocumentData')
            ->with(current($this->cachableStorages), 'id')
            ->willReturn($documentDriverDataMock);

        $this->cacheMock
            ->expects($this->once())
            ->method('get')
            ->with('document_manager_cache_item_deliveries_id', $this->callback(function ($parameter) {
                return is_callable($parameter);
            }))
            ->willReturnCallback(function ($key, $callback) use (&$save) {
                return $callback($this->createMock(ItemInterface::class), $save);
            });

        $this->assertSame($documentDriverDataMock, $this->subject->getDocumentData(current($this->cachableStorages), 'id'));
        $this->assertTrue($save);
    }

    public function testGetNullDocumentDataWithCache(): void
    {
        $save = true;

        $this->driverMock
            ->expects($this->once())
            ->method('getDocumentData')
            ->with(current($this->cachableStorages), 'id')
            ->willReturn(null);

        $this->cacheMock
            ->expects($this->once())
            ->method('get')
            ->with('document_manager_cache_item_deliveries_id', $this->callback(function ($parameter) {
                return is_callable($parameter);
            }))
            ->willReturnCallback(function ($key, $callback) use (&$save) {
                return $callback($this->createMock(ItemInterface::class), $save);
            });

        $this->assertNull($this->subject->getDocumentData(current($this->cachableStorages), 'id'));
        $this->assertFalse($save);
    }

    public function testNotSaveSoftDeletedData(): void
    {
        $save = true;

        $documentDriverDataMock = $this->createMock(DocumentDriverDataInterface::class);
        $documentDriverDataMock
            ->expects($this->any())
            ->method('getData')
            ->willReturn(['isDeleted' => true]);

        $this->driverMock
            ->expects($this->once())
            ->method('getDocumentData')
            ->with(current($this->cachableStorages), 'id')
            ->willReturn($documentDriverDataMock);

        $this->cacheMock
            ->expects($this->once())
            ->method('get')
            ->with('document_manager_cache_item_deliveries_id', $this->callback(function ($parameter) {
                return is_callable($parameter);
            }))
            ->willReturnCallback(function ($key, $callback) use (&$save) {
                return $callback($this->createMock(ItemInterface::class), $save);
            });

        $this->assertSame($documentDriverDataMock, $this->subject->getDocumentData(current($this->cachableStorages), 'id'));
        $this->assertFalse($save);
    }

    public function testSaveDocumentData(): void
    {
        $documentDriverDataMock = $this->createMock(DocumentDriverDataInterface::class);

        $this->cacheMock
            ->expects($this->never())
            ->method('delete');

        $this->cacheMock
            ->expects($this->never())
            ->method('invalidateTags');

        $this->subject->saveDocumentData('foo', $documentDriverDataMock);
    }

    public function testSaveDocumentDataWithCache(): void
    {
        $documentDriverDataMock = $this->createMock(DocumentDriverDataInterface::class);
        $documentDriverDataMock
            ->expects($this->once())
            ->method('getId')
            ->willReturn('id');

        $this->cacheMock
            ->expects($this->once())
            ->method('delete')
            ->with('document_manager_cache_item_deliveries_id');

        $this->cacheMock
            ->expects($this->once())
            ->method('invalidateTags')
            ->with(['document_manager_tag_collection_deliveries']);

        $this->subject->saveDocumentData(current($this->cachableStorages), $documentDriverDataMock);
    }

    public function testDeleteDocumentData(): void
    {
        $documentDriverDataMock = $this->createMock(DocumentDriverDataInterface::class);

        $this->cacheMock
            ->expects($this->never())
            ->method('delete');

        $this->cacheMock
            ->expects($this->never())
            ->method('invalidateTags');

        $this->subject->deleteDocumentData('foo', $documentDriverDataMock);
    }

    public function testDeleteDocumentDataWithCache(): void
    {
        $documentDriverDataMock = $this->createMock(DocumentDriverDataInterface::class);
        $documentDriverDataMock
            ->expects($this->once())
            ->method('getId')
            ->willReturn('id');

        $this->cacheMock
            ->expects($this->once())
            ->method('delete')
            ->with('document_manager_cache_item_deliveries_id');

        $this->cacheMock
            ->expects($this->once())
            ->method('invalidateTags')
            ->with(['document_manager_tag_collection_deliveries']);

        $this->subject->deleteDocumentData(current($this->cachableStorages), $documentDriverDataMock);
    }

    public function testGetDocumentsCollectionData(): void
    {
        $documentDriverDataMock = $this->createMock(DocumentDriverDataInterface::class);
        $iterator = (new ArrayObject([$documentDriverDataMock]))->getIterator();

        $this->driverMock
            ->expects($this->once())
            ->method('getDocumentsCollectionData')
            ->with('foo', ['criteria'], 1, 2)
            ->willReturn($iterator);

        $this->cacheMock
            ->expects($this->never())
            ->method('get');

        $this->assertSame($iterator, $this->subject->getDocumentsCollectionData('foo', ['criteria'], 1, 2));
    }

    public function testGetDocumentsCollectionDataWithCache(): void
    {
        $save = true;

        $documentDriverDataMock = $this->createMock(DocumentDriverDataInterface::class);
        $iterator = (new ArrayObject([$documentDriverDataMock]))->getIterator();

        $this->driverMock
            ->expects($this->once())
            ->method('getDocumentsCollectionData')
            ->with(current($this->cachableStorages), ['criteria'], 1, 2)
            ->willReturn($iterator);

        $this->cacheMock
            ->expects($this->once())
            ->method('get')
            ->with(sprintf('document_manager_cache_collection_deliveries_%s', md5(json_encode(['criteria'], JSON_THROW_ON_ERROR))), $this->callback(function ($parameter) {
                return is_callable($parameter);
            }))
            ->willReturnCallback(function ($key, $callback) use (&$save) {
                return $callback($this->createMock(ItemInterface::class), $save);
            });

        $this->assertSame(
            iterator_to_array($iterator),
            $this->subject->getDocumentsCollectionData(current($this->cachableStorages), ['criteria'], 1, 2),
        );
        $this->assertTrue($save);
    }

    public function testSaveDocumentCollectionData(): void
    {
        $documentDriverDataMock = $this->createMock(DocumentDriverDataInterface::class);
        $iterator = (new ArrayObject([$documentDriverDataMock]))->getIterator();

        $this->cacheMock
            ->expects($this->never())
            ->method('delete');

        $this->cacheMock
            ->expects($this->never())
            ->method('invalidateTags');

        $this->subject->saveDocumentsCollectionData('foo', $iterator);
    }

    public function testSaveDocumentCollectionDataWithCache(): void
    {
        $documentDriverDataMock = $this->createMock(DocumentDriverDataInterface::class);
        $documentDriverDataMock
            ->expects($this->once())
            ->method('getId')
            ->willReturn('id');
        $iterator = (new ArrayObject([$documentDriverDataMock]))->getIterator();

        $this->cacheMock
            ->expects($this->once())
            ->method('delete')
            ->with('document_manager_cache_item_deliveries_id');

        $this->cacheMock
            ->expects($this->once())
            ->method('invalidateTags')
            ->with(['document_manager_tag_collection_deliveries']);

        $this->subject->saveDocumentsCollectionData(current($this->cachableStorages), $iterator);
    }

    public function testDeleteDocumentsCollectionData(): void
    {
        $documentDriverDataMock = $this->createMock(DocumentDriverDataInterface::class);
        $iterator = (new ArrayObject([$documentDriverDataMock]))->getIterator();

        $this->cacheMock
            ->expects($this->never())
            ->method('delete');

        $this->cacheMock
            ->expects($this->never())
            ->method('invalidateTags');

        $this->subject->deleteDocumentsCollectionData('foo', $iterator);
    }

    public function testDeleteDocumentsCollectionDataWithCache(): void
    {
        $documentDriverDataMock = $this->createMock(DocumentDriverDataInterface::class);
        $documentDriverDataMock
            ->expects($this->once())
            ->method('getId')
            ->willReturn('id');
        $iterator = (new ArrayObject([$documentDriverDataMock]))->getIterator();

        $this->cacheMock
            ->expects($this->once())
            ->method('delete')
            ->with('document_manager_cache_item_deliveries_id');

        $this->cacheMock
            ->expects($this->once())
            ->method('invalidateTags')
            ->with(['document_manager_tag_collection_deliveries']);

        $this->subject->deleteDocumentsCollectionData(current($this->cachableStorages), $iterator);
    }
}
