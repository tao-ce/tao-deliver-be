<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\Delivery;

use App\Domain\Delivery\Model\Statistics\DeliveryStatistics;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Repository\DeliveryExecutionRepository;
use App\Service\Delivery\GenerateDeliveryStatisticsService;
use App\Tests\Traits\DomainTestingTrait;
use ArrayIterator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GenerateDeliveryStatisticsServiceTest extends TestCase
{
    use DomainTestingTrait;

    /** @var DeliveryExecutionRepository|MockObject */
    private $repositoryMock;

    /** @var GenerateDeliveryStatisticsService */
    private $subject;

    public function setUp(): void
    {
        $this->repositoryMock = $this->createMock(DeliveryExecutionRepository::class);

        $this->subject = new GenerateDeliveryStatisticsService($this->repositoryMock);
    }

    public function testGenerate(): void
    {
        $delivery = $this->createTestDelivery();

        $deliveryExecution1 = $this->createTestDeliveryExecution();
        $deliveryExecution1->setStatus(DeliveryExecution::STATUS_INITIAL);

        $deliveryExecution2 = $this->createTestDeliveryExecution();
        $deliveryExecution2->setStatus(DeliveryExecution::STATUS_INTERACTING);

        $deliveryExecution3 = $this->createTestDeliveryExecution();
        $deliveryExecution3->setStatus(DeliveryExecution::STATUS_CLOSED);

        $this->repositoryMock
            ->expects($this->once())
            ->method('findByDeliveryId')
            ->with($delivery->getId())
            ->willReturn(new ArrayIterator([
                $deliveryExecution1,
                $deliveryExecution2,
                $deliveryExecution3,
            ]));

        $result = $this->subject->generate($delivery);

        $this->assertInstanceOf(DeliveryStatistics::class, $result);
        $this->assertEquals(
            [
                'totalDeliveryExecutions' => 3,
                'deliveryExecutionsStatusInitial' => 1,
                'deliveryExecutionsStatusInteracting' => 1,
                'deliveryExecutionsStatusClosed' => 1,
            ],
            $result->getStatistics(),
        );
    }
}
