<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\Battery;

use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDelivery;
use App\Domain\Battery\Model\BatteryDistribution;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Repository\BatteryDistributionRepository;
use App\Service\Battery\BatteryPasswordValidationService;
use App\Service\Battery\Dto\BatteryPasswordValidationCommand;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use App\Tests\Traits\DataStoreTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class BatteryPasswordValidationServiceTest extends TestCase
{
    use DomainTestingTrait;
    use DataStoreTestingTrait;

    private readonly DeliveryExecutionService $deliveryExecutionService;
    private readonly LoggerInterface $logger;
    private readonly BatteryPasswordValidationService $sut;
    private readonly BatteryPasswordValidationCommand $command;
    private readonly BatteryDistributionRepository $batteryDistributionRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deliveryExecutionService = $this->createMock(DeliveryExecutionService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->batteryDistributionRepository = $this->createMock(BatteryDistributionRepository::class);
        $this->sut = new BatteryPasswordValidationService(
            $this->deliveryExecutionService,
            $this->logger,
            $this->batteryDistributionRepository,
        );
        $this->command = new BatteryPasswordValidationCommand(
            'pass',
            'deliveryId',
            'deliveryExecutionId',
        );
    }

    public function testValidateWithNoError(): void
    {
        $batteryId = 'batteryId';
        $userId = 'userId';

        $batteryDelivery = $this
            ->getMockBuilder(BatteryDelivery::class)
            ->enableOriginalConstructor()
            ->setConstructorArgs([$batteryId, 'pass', 1])
            ->getMock();
        $batteryDelivery
            ->expects($this->once())
            ->method('isPasswordProtected')
            ->willReturn(true);
        $batteryDelivery
            ->expects($this->once())
            ->method('matchPassword')
            ->with('pass')
            ->willReturn(true);

        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $deliveryExecution
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn(
                [
                    'battery_id' => $batteryId,
                    'user_id' => $userId,
                ],
            );

        $battery = $this->createMock(Battery::class);
        $battery
            ->expects($this->once())
            ->method('getDelivery')
            ->with('deliveryId')
            ->willReturn($batteryDelivery);

        $batteryDistribution = $this
            ->getMockBuilder(BatteryDistribution::class)
            ->enableOriginalConstructor()
            ->setConstructorArgs(['id', $userId, $battery])
            ->getMock();

        $this->batteryDistributionRepository
            ->expects($this->once())
            ->method('findByBatteryAndUserId')
            ->with($batteryId, $userId)
            ->willReturn($batteryDistribution);

        $this->deliveryExecutionService
            ->expects($this->once())
            ->method('findDeliveryExecutionOrFail')
            ->with('deliveryExecutionId')
            ->willReturn($deliveryExecution);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $this->sut->validate($this->command);
    }

    public function testValidateWithoutBatteryIdWillThrowException(): void
    {
        $deliveryExecution = $this->createMock(DeliveryExecution::class);

        $deliveryExecution
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn([]);

        $this->deliveryExecutionService
            ->expects($this->once())
            ->method('findDeliveryExecutionOrFail')
            ->with('deliveryExecutionId')
            ->willReturn($deliveryExecution);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('There is not battery associated to the delivery execution');

        $this->sut->validate($this->command);
    }

    public function testValidateWithoutBatteryDeliveryWillThrowException(): void
    {
        $batteryId = 'batteryId';
        $userId = 'userId';
        $deliveryExecution = $this->createMock(DeliveryExecution::class);

        $deliveryExecution
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn(
                [
                    'battery_id' => $batteryId,
                    'user_id' => $userId,
                ],
            );

        $battery = $this->createMock(Battery::class);
        $battery
            ->expects($this->once())
            ->method('getDelivery')
            ->with('deliveryId')
            ->willReturn(null);

        $batteryDistribution = $this
            ->getMockBuilder(BatteryDistribution::class)
            ->enableOriginalConstructor()
            ->setConstructorArgs(['id', $userId, $battery])
            ->getMock();

        $this->batteryDistributionRepository
            ->expects($this->once())
            ->method('findByBatteryAndUserId')
            ->with($batteryId, $userId)
            ->willReturn($batteryDistribution);

        $this->deliveryExecutionService
            ->expects($this->once())
            ->method('findDeliveryExecutionOrFail')
            ->with('deliveryExecutionId')
            ->willReturn($deliveryExecution);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The delivery does not exist in the battery');

        $this->sut->validate($this->command);
    }

    public function testValidateWithWrongPasswordWillThrowException(): void
    {
        $batteryId = 'batteryId';
        $userId = 'userId';
        $deliveryExecution = $this->createMock(DeliveryExecution::class);

        $batteryDelivery = $this
            ->getMockBuilder(BatteryDelivery::class)
            ->enableOriginalConstructor()
            ->setConstructorArgs([$batteryId, 'correct-pass', 1])
            ->getMock();
        $batteryDelivery
            ->expects($this->once())
            ->method('isPasswordProtected')
            ->willReturn(true);
        $batteryDelivery
            ->expects($this->once())
            ->method('matchPassword')
            ->with('pass')
            ->willReturn(false);

        $deliveryExecution
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn(
                [
                    'battery_id' => $batteryId,
                    'user_id' => $userId,
                ],
            );

        $battery = $this->createMock(Battery::class);
        $battery
            ->expects($this->once())
            ->method('getDelivery')
            ->with('deliveryId')
            ->willReturn($batteryDelivery);

        $batteryDistribution = $this
            ->getMockBuilder(BatteryDistribution::class)
            ->enableOriginalConstructor()
            ->setConstructorArgs(['id', $userId, $battery])
            ->getMock();

        $this->batteryDistributionRepository
            ->expects($this->once())
            ->method('findByBatteryAndUserId')
            ->with($batteryId, $userId)
            ->willReturn($batteryDistribution);

        $this->deliveryExecutionService
            ->expects($this->once())
            ->method('findDeliveryExecutionOrFail')
            ->with('deliveryExecutionId')
            ->willReturn($deliveryExecution);

        $this->expectException(UnauthorizedHttpException::class);
        $this->expectExceptionMessage('Unauthorized access');

        $this->sut->validate($this->command);
    }
}
