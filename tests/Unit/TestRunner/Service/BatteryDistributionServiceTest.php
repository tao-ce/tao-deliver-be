<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\Service;

use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDistribution;
use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Repository\DeliveryRepository;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use App\TestRunner\Service\BatteryDistributionService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Psr\Log\LoggerInterface;

class BatteryDistributionServiceTest extends KernelTestCase
{
    private readonly DeliveryExecutionService&MockObject $deliveryExecutionService;
    private readonly DeliveryRepository&MockObject $deliveryRepository;
    private readonly LoggerInterface&MockObject $logger;
    private readonly BatteryDistributionService $sut;

    protected function setUp(): void
    {
        $this->deliveryExecutionService = $this->createMock(DeliveryExecutionService::class);
        $this->deliveryRepository = $this->createMock(DeliveryRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new BatteryDistributionService(
            $this->deliveryExecutionService,
            $this->deliveryRepository,
            $this->logger,
        );
    }

    public function testDeleteDeliveryExecutions(): void
    {
        $battery = $this->createMock(Battery::class);
        $battery->deliveries = [
            (object)['id' => 1],
            (object)['id' => 2],
        ];

        $batteryDistribution = $this->createMock(BatteryDistribution::class);
        /** @noinspection PhpReadonlyPropertyWrittenOutsideDeclarationScopeInspection */
        $batteryDistribution->battery = $battery;

        $deliveryExecution1 = $this->createConfiguredMock(DeliveryExecution::class, ['getId' => 'deliveryExecution1']);
        $deliveryExecution2 = $this->createConfiguredMock(DeliveryExecution::class, ['getId' => 'deliveryExecution2']);

        $delivery1 = $this->createMock(Delivery::class);
        $delivery2 = $this->createMock(Delivery::class);

        $delivery1->method('getId')->willReturn('deliveryId1');
        $delivery2->method('getId')->willReturn('deliveryId2');

        $this->deliveryRepository
            ->method('find')
            ->willReturnOnConsecutiveCalls($delivery1, $delivery2);

        $this->deliveryExecutionService
            ->method('getDeliveryExecution')
            ->willReturnOnConsecutiveCalls($deliveryExecution1, $deliveryExecution2);

        $this->deliveryExecutionService
            ->expects($this->exactly(2))
            ->method('deleteDeliveryExecution');

        $this->logger->expects($this->never())->method('warning');

        $this->sut->deleteDeliveryExecutionsLinkedToBatteryDistribution($batteryDistribution, $deliveryExecution1);
    }

    public function testHandlesNotFoundHttpException(): void
    {
        $battery = $this->createMock(Battery::class);
        $battery->deliveries = [
            (object)['id' => 99],
        ];

        $batteryDistribution = $this->createMock(BatteryDistribution::class);
        /** @noinspection PhpReadonlyPropertyWrittenOutsideDeclarationScopeInspection */
        $batteryDistribution->battery = $battery;

        $deliveryExecution1 = $this->createConfiguredMock(DeliveryExecution::class, ['getId' => 'deliveryExecution1']);

        $delivery1 = $this->createMock(Delivery::class);
        $delivery2 = $this->createMock(Delivery::class);

        $delivery1->method('getId')->willReturn('deliveryId1');
        $delivery2->method('getId')->willReturn('deliveryId2');

        $this->deliveryRepository
            ->method('find')
            ->willReturnOnConsecutiveCalls($delivery1, $delivery2);

        $this->deliveryExecutionService
            ->method('getDeliveryExecution')
            ->willThrowException(new NotFoundHttpException('Execution not found'));

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('[deliveryExecution1] Failed to find delivery execution'),
                $this->arrayHasKey('exception'),
            );

        $this->sut->deleteDeliveryExecutionsLinkedToBatteryDistribution(
            $batteryDistribution,
            $deliveryExecution1,
        );
    }
}
