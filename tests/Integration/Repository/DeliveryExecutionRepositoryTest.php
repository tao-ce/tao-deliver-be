<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Repository\DeliveryExecutionRepository;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use Carbon\Carbon;
use OAT\Bundle\DocumentManagerBundle\Document\Collection\DocumentCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Tests\Traits\MemoryLeaksTrait;

class DeliveryExecutionRepositoryTest extends KernelTestCase
{
    use DocumentTestingTrait;
    use DomainTestingTrait;
    use MemoryLeaksTrait;

    public const SIZE_TEST_COLLECTION = 1000;

    /** @var DeliveryExecutionRepository */
    private $subject;

    protected function setUp(): void
    {
        static::bootKernel();

        Carbon::setTestNow(Carbon::create(2019, 1, 1, 0, 0, 0, 'Europe/Luxembourg'));

        $this->setUpTestDocumentManager();

        $this->subject = static::getContainer()->get(DeliveryExecutionRepository::class);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow();
    }

    public function testFindByDeliveryId(): void
    {
        $deliveryExecution1 = $this->createTestDeliveryExecution('userId1#deliveryId#resultId#tenantId');
        $deliveryExecution2 = $this->createTestDeliveryExecution('userId2#deliveryId#resultId#tenantId');
        $deliveryExecution3 = $this->createTestDeliveryExecution('userId3#deliveryId#resultId#tenantId');

        $collection = new DocumentCollection([
            $deliveryExecution1,
            $deliveryExecution2,
            $deliveryExecution3,
        ]);

        $this->saveDocumentCollection($collection);

        $expectedIds = array_map(
            fn($execution) => $execution->getId(),
            iterator_to_array($collection),
        );

        $actualExecutions = iterator_to_array($this->subject->findByDeliveryId('deliveryId'));

        $this->assertCount(3, $actualExecutions);
        foreach ($actualExecutions as $execution) {
            $this->assertContains($execution->getId(), $expectedIds);
            $this->assertInstanceOf(DeliveryExecution::class, $execution);
            $this->assertNotNull($execution->getStartedAt());
            $this->assertNotNull($execution->getUpdatedAt());
        }

        $this->assertEmpty(iterator_to_array($this->subject->findByDeliveryId('invalid')));
    }

    public function testFindMemoryLeak()
    {
        $deliveryExecutionCollection = [];
        for ($i = 0; $i < self::SIZE_TEST_COLLECTION; $i++) {
            $deliveryExecutionId = sprintf('userId%d#deliveryId#resultId#tenantId', $i);
            $deliveryExecutionCollection[$deliveryExecutionId] = $this->createTestDeliveryExecution($deliveryExecutionId);
        }
        $this->saveDocumentCollection(new DocumentCollection($deliveryExecutionCollection));

        $searchIds = array_keys($deliveryExecutionCollection);
        unset($deliveryExecutionCollection);

        // test leaks
        $this->measureLeakForMethod($this->subject, 'find', $searchIds);
    }
}
