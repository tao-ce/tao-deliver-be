<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Domain\Delivery\Model\Delivery;
use App\Repository\DeliveryExecutionRepository;
use App\Repository\DeliveryRepository;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use Carbon\Carbon;
use OAT\Bundle\DocumentManagerBundle\Document\Collection\DocumentCollection;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DeliveryRepositoryTest extends KernelTestCase
{
    use DocumentTestingTrait;
    use DomainTestingTrait;

    /** @var DeliveryExecutionRepository */
    private $subject;

    protected function setUp(): void
    {
        static::bootKernel();

        Carbon::setTestNow(Carbon::create(2019, 1, 1, 0, 0, 0, 'Europe/Luxembourg'));

        $this->setUpTestDocumentManager();

        $this->subject = static::getContainer()->get(DeliveryRepository::class);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow();
    }

    public function testFindByTenantIdSuccess(): void
    {
        $delivery1 = $this->createTestDelivery('1', 'tenantId1');
        $delivery2 = $this->createTestDelivery('2', 'tenantId1');
        $delivery3 = $this->createTestDelivery('3', 'tenantId3');

        $collection = new DocumentCollection([
            $delivery1,
            $delivery2,
            $delivery3,
        ]);

        $this->saveDocumentCollection($collection);

        $expectedCollection = $collection->remove($collection->get('3'));

        $this->assertEquals(
            $expectedCollection,
            $this->subject->findCollectionByTenantId('tenantId1'),
        );
    }

    public function testFind(): void
    {
        $nonDeletedDelivery = $this->createTestDelivery('non-deleted-delivery');
        $deletedDelivery = $this->createTestDelivery('deleted-delivery', isDeleted: true);

        $collection = new DocumentCollection([
            $nonDeletedDelivery,
            $deletedDelivery,
        ]);

        $this->saveDocumentCollection($collection);

        $this->assertEquals($nonDeletedDelivery, $this->subject->find('non-deleted-delivery'));

        $this->expectException(DocumentNotFoundException::class);
        $this->expectExceptionMessage(sprintf("Document class '%s' with id 'deleted-delivery' not found", Delivery::class));
        $this->subject->find('deleted-delivery');
    }

    public function testFindCollection(): void
    {
        $nonDeletedDelivery = $this->createTestDelivery('non-deleted-delivery');
        $deletedDelivery = $this->createTestDelivery('deleted-delivery', isDeleted: true);

        $collection = new DocumentCollection([
            $nonDeletedDelivery,
            $deletedDelivery,
        ]);

        $this->saveDocumentCollection($collection);

        $fetchedCollection = $this->subject->findCollection();

        $this->assertSame(1, $fetchedCollection->count());
        $this->assertSame('non-deleted-delivery', $fetchedCollection->getIterator()->current()->getId());
    }
}
