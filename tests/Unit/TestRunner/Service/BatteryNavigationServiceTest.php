<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\Service;

use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDelivery;
use App\Domain\Battery\Model\BatteryDistribution;
use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Generator\UrlGenerator;
use App\Lti\LtiCustomSettings;
use App\Repository\BatteryDistributionRepository;
use App\Repository\DeliveryRepository;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\TestRunner\Service\BatteryNavigationService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BatteryNavigationServiceTest extends KernelTestCase
{
    private readonly Battery $battery;
    private readonly DeliveryExecution $deliveryExecution;
    private readonly DeliveryExecution $nextDeliveryExecution;
    private readonly DeliveryExecutionServiceInterface $deliveryExecutionService;
    private readonly UrlGenerator $urlGenerator;
    private readonly DeliveryRepository $deliveryRepository;
    private readonly Delivery $nextDelivery;
    private readonly BatteryDistributionRepository $batteryDistributionRepository;
    private readonly BatteryNavigationService $sut;

    public function setUp(): void
    {
        $this->battery = $this->createMock(Battery::class);
        $this->battery
            ->method('getId')
            ->willReturn('123');
        $this->deliveryExecution = $this->createMock(DeliveryExecution::class);
        $this->nextDelivery = $this->createMock(Delivery::class);
        $this->nextDeliveryExecution = $this->createMock(DeliveryExecution::class);
        $this->deliveryExecutionService = $this->createMock(DeliveryExecutionServiceInterface::class);
        $this->deliveryExecutionService
            ->expects($this->never())
            ->method('saveDeliveryExecution');
        $this->deliveryRepository = $this->createMock(DeliveryRepository::class);
        $this->urlGenerator = $this->createMock(UrlGenerator::class);
        $this->batteryDistributionRepository = $this->createMock(BatteryDistributionRepository::class);

        $this->sut = new BatteryNavigationService(
            static::getContainer()->get(LtiCustomSettings::class),
            $this->deliveryExecutionService,
            $this->deliveryRepository,
            $this->batteryDistributionRepository,
            $this->urlGenerator,
        );
    }

    public function testGetContextForNonBatteryDelivery(): void
    {
        $this->deliveryExecution
            ->method('getOriginalLtiLaunchParameters')
            ->willReturn([]);

        $this->assertNull($this->sut->getBatteryContext($this->deliveryExecution));
    }

    public function testGetContext(): void
    {
        $this->deliveryExecution
            ->method('getOriginalLtiLaunchParameters')
            ->willReturn([
                'battery_id' => $this->battery->getId(),
                'user_id' => 'userId',
            ]);
        $this->deliveryExecution
            ->method('getDeliveryId')
            ->willReturn('currentDeliveryId');
        $this->deliveryExecution
            ->method('getId')
            ->willReturn('currentDeliveryExecutionId');

        $batteryDistribution = $this->createBatteryDistributionMock($this->battery);

        $this->batteryDistributionRepository
            ->expects($this->once())
            ->method('findByBatteryAndUserId')
            ->with($this->battery->getId(), 'userId')
            ->willReturn($batteryDistribution);

        $currentButteryDelivery = $this->createBatteryDeliveryMock('currentDeliveryId', 1);
        $currentButteryDelivery
            ->expects($this->once())
            ->method('isPasswordProtected')
            ->willReturn(true);

        $nextButteryDelivery = $this->createBatteryDeliveryMock('nextDeliveryId', 2);
        $nextButteryDelivery
            ->expects($this->once())
            ->method('isPasswordProtected')
            ->willReturn(true);

        $this->battery
            ->method('getDelivery')
            ->with('currentDeliveryId')
            ->willReturn($currentButteryDelivery);
        $this->battery
            ->method('getNextDelivery')
            ->with('currentDeliveryId')
            ->willReturn($nextButteryDelivery);

        $this->deliveryRepository
            ->expects($this->once())
            ->method('find')
            ->with('nextDeliveryId')
            ->willReturn($this->nextDelivery);

        $this->deliveryExecutionService
            ->expects($this->once())
            ->method('getDeliveryExecution')
            ->with(
                $this->nextDelivery,
                [
                    'battery_id' => $this->battery->getId(),
                    'user_id' => 'userId',
                    'id' => 'move',
                    'name' => 'move',
                    'parameters' => [
                        'itemIdentifier' => 'item-3',
                    ],
                ],
            )
            ->willReturn($this->nextDeliveryExecution);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with(
                'api_v1_battery_continue',
                [
                    'id' => 'currentDeliveryExecutionId',
                ],
            )
            ->willReturn('nextDeliveryExecutionUrl');

        $this->assertEquals(
            [
                'currentDelivery' => [
                    'id' => 'currentDeliveryId',
                    'order' => 1,
                    'isPasswordProtected' => true,
                ],
                'nextDelivery' => [
                    'id' => 'nextDeliveryId',
                    'order' => 2,
                    'isPasswordProtected' => true,
                ],
                'nextDeliveryExecutionUrl' => 'nextDeliveryExecutionUrl',
                'batteryDistribution' => [
                    'id' => '',
                    'locale' => null,
                ],
            ],
            $this->sut->getBatteryContext(
                $this->deliveryExecution,
                [
                    'id' => 'move',
                    'name' => 'move',
                    'parameters' => [
                        'itemIdentifier' => 'item-3',
                    ],
                ],
            ),
        );
    }

    public function testGetContextForLastDelivery(): void
    {
        $this->deliveryExecution
            ->method('getOriginalLtiLaunchParameters')
            ->willReturn([
                'battery_id' => $this->battery->getId(),
                'user_id' => 'userId',
            ]);
        $this->deliveryExecution
            ->method('getDeliveryId')
            ->willReturn('currentDeliveryId');

        $batteryDistribution = $this->createBatteryDistributionMock($this->battery);

        $this->batteryDistributionRepository
            ->expects($this->once())
            ->method('findByBatteryAndUserId')
            ->with($this->battery->getId(), 'userId')
            ->willReturn($batteryDistribution);

        $currentButteryDelivery = $this->createBatteryDeliveryMock('currentDeliveryId', 1);
        $currentButteryDelivery
            ->expects($this->never())
            ->method('isPasswordProtected');

        $this->battery
            ->method('getDelivery')
            ->with('currentDeliveryId')
            ->willReturn($currentButteryDelivery);
        $this->battery
            ->method('getNextDelivery')
            ->with('currentDeliveryId')
            ->willReturn(null);

        $this->deliveryRepository
            ->expects($this->never())
            ->method('find');

        $this->assertNull($this->sut->getBatteryContext($this->deliveryExecution));
    }

    private function createBatteryDistributionMock(Battery $battery): BatteryDistribution|MockObject
    {
        return $this
            ->getMockBuilder(BatteryDistribution::class)
            ->enableOriginalConstructor()
            ->setConstructorArgs(['id', 'userId', $battery])
            ->getMock();
    }

    private function createBatteryDeliveryMock(string $id, int $order): BatteryDelivery|MockObject
    {
        return $this
            ->getMockBuilder(BatteryDelivery::class)
            ->enableOriginalConstructor()
            ->setConstructorArgs([$id, 'pass', $order])
            ->getMock();
    }
}
