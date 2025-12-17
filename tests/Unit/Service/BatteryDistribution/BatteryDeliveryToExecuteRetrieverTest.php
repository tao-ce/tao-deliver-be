<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\BatteryDistribution;

use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDelivery;
use App\Domain\Battery\Model\BatteryDistribution;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\BatteryDistribution\BatteryDeliveryToExecuteRetriever;
use App\Service\DeliveryExecution\Contract\BatteryDeliveryFilterInterface;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BatteryDeliveryToExecuteRetrieverTest extends TestCase
{
    private readonly DeliveryExecutionServiceInterface $deliveryExecutionServiceInterface;
    private readonly BatteryDeliveryToExecuteRetriever $sut;

    protected function setUp(): void
    {
        $this->deliveryExecutionServiceInterface = $this->createMock(DeliveryExecutionServiceInterface::class);

        $this->sut = new BatteryDeliveryToExecuteRetriever(
            $this->deliveryExecutionServiceInterface,
            [$this->createMock(BatteryDeliveryFilterInterface::class)],
        );
    }

    public function testRetrieveCurrentOrNextDelivery(): void
    {
        $deliveryToExecute = $this->createBatteryDeliveryMock();

        $battery = $this->createBatteryMock(Battery::MODE_ALL_IN_SEQUENCE, $deliveryToExecute);

        $batteryDistribution = $this->createBatteryDistributionMock($battery);

        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $deliveryExecution
            ->expects($this->once())
            ->method('isStateFinal')
            ->willReturn(false);

        $this->deliveryExecutionServiceInterface
            ->method('createDeliveryExecutionId')
            ->with(
                $deliveryToExecute->id,
                $battery->tenantId,
                ['user_id' => 'userId'],
            )
            ->willReturn('deliveryExecutionId');

        $this->deliveryExecutionServiceInterface
            ->method('findDeliveryExecution')
            ->with('deliveryExecutionId')
            ->willReturn($deliveryExecution);

        $this->assertEquals($deliveryToExecute, $this->sut->retrieve($batteryDistribution, ['user_id' => 'userId']));
    }

    public function testRetrieveRandomDelivery(): void
    {
        $deliveryToExecute = $this->createBatteryDeliveryMock();
        $battery = $this->createBatteryMock(Battery::MODE_RANDOM_DELIVERY, $deliveryToExecute);
        $batteryDistribution = $this->createBatteryDistributionMock($battery);

        $this->assertEquals($deliveryToExecute, $this->sut->retrieve($batteryDistribution, ['user_id' => 'userId']));
    }

    public function testRetrieveNoDeliveryToExecute(): void
    {
        $executedDelivery = $this->createBatteryDeliveryMock();

        $battery = $this->createBatteryMock(Battery::MODE_ALL_IN_SEQUENCE, $executedDelivery);

        $batteryDistribution = $this->createBatteryDistributionMock($battery);

        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $deliveryExecution
            ->expects($this->once())
            ->method('isStateFinal')
            ->willReturn(true);

        $this->deliveryExecutionServiceInterface
            ->method('createDeliveryExecutionId')
            ->with(
                $executedDelivery->id,
                $battery->tenantId,
                ['user_id' => 'userId'],
            )
            ->willReturn('deliveryExecutionId');
        $this->deliveryExecutionServiceInterface
            ->method('findDeliveryExecution')
            ->with('deliveryExecutionId')
            ->willReturn($deliveryExecution);

        $this->assertEquals($executedDelivery, $this->sut->retrieve($batteryDistribution, ['user_id' => 'userId']));
    }

    public function testRetrieveNotExecutedDelivery(): void
    {
        $notExecutedDelivery = $this->createBatteryDeliveryMock();

        $battery = $this->createBatteryMock(Battery::MODE_ALL_IN_SEQUENCE, $notExecutedDelivery);

        $batteryDistribution = $this->createBatteryDistributionMock($battery);

        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $deliveryExecution
            ->expects($this->never())
            ->method('isStateFinal');

        $this->deliveryExecutionServiceInterface
            ->method('createDeliveryExecutionId')
            ->with(
                $notExecutedDelivery->id,
                $battery->tenantId,
                ['user_id' => 'userId'],
            )
            ->willReturn('deliveryExecutionId');
        $this->deliveryExecutionServiceInterface
            ->method('findDeliveryExecution')
            ->with('deliveryExecutionId')
            ->willReturn(null);

        $this->assertEquals($notExecutedDelivery, $this->sut->retrieve($batteryDistribution, ['user_id' => 'userId']));
    }

    private function createBatteryMock(string $mode, BatteryDelivery $batteryDelivery): Battery
    {
        $deliveries = [$batteryDelivery];
        if ($mode === Battery::MODE_RANDOM_DELIVERY) {
            for ($i = 0; $i < 999; $i++) {
                $deliveries[] = $this->createBatteryDeliveryMock();
            }
        }
        return new Battery(
            'batteryId',
            'batteryTenantId',
            'batteryName',
            'batteryDescription',
            Battery::STATUS_ACTIVE,
            $mode,
            $deliveries,
        );
    }

    private function createBatteryDeliveryMock(): BatteryDelivery|MockObject
    {
        return $this
            ->getMockBuilder(BatteryDelivery::class)
            ->enableOriginalConstructor()
            ->setConstructorArgs(['deliveryId', null, null])
            ->getMock();
    }

    private function createBatteryDistributionMock(Battery $battery): BatteryDistribution|MockObject
    {
        return $this
            ->getMockBuilder(BatteryDistribution::class)
            ->enableOriginalConstructor()
            ->setConstructorArgs(['id', 'userId', $battery])
            ->getMock();
    }
}
