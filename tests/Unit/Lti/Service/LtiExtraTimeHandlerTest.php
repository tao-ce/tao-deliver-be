<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Lti\Service;

use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDelivery;
use App\Domain\Battery\Model\BatteryDistribution;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Generator\UuidGenerator;
use App\Lti\LtiCustomSettings;
use App\Lti\Service\LtiExtraTimeHandler;
use App\Repository\BatteryDistributionRepository;
use App\Repository\DeliveryExecutionRepository;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use DateTime;
use Exception;
use OAT\Library\TaoTimerClient\Client\GetTimerException;
use OAT\Library\TaoTimerClient\Model\TimerDefinition;
use OAT\Library\TaoTimerClient\Model\TimerDetail;
use OAT\Library\TaoTimerClient\Service\ProctoringAcsService;
use OAT\Library\TaoTimerClient\Service\TimerServiceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Throwable;

class LtiExtraTimeHandlerTest extends TestCase
{
    private readonly LtiExtraTimeHandler $subject;
    private readonly LoggerInterface $loggerMock;
    private readonly UuidGenerator $uuidGeneratorMock;
    private readonly ProctoringAcsService $proctoringAcsServiceMock;
    private readonly TimerServiceInterface $timerServiceMock;
    private readonly LtiCustomSettings $ltiCustomSettingsMock;
    private readonly BatteryDistributionRepository $batteryDistributionRepositoryMock;
    private readonly DeliveryExecutionRepository $deliveryExecutionRepositoryMock;
    private readonly DeliveryExecutionService $deliveryExecutionServiceMock;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->uuidGeneratorMock = $this->createMock(UuidGenerator::class);
        $this->proctoringAcsServiceMock = $this->createMock(ProctoringAcsService::class);
        $this->timerServiceMock = $this->createMock(TimerServiceInterface::class);
        $this->ltiCustomSettingsMock = $this->createMock(LtiCustomSettings::class);
        $this->batteryDistributionRepositoryMock = $this->createMock(BatteryDistributionRepository::class);
        $this->deliveryExecutionRepositoryMock = $this->createMock(DeliveryExecutionRepository::class);
        $this->deliveryExecutionServiceMock = $this->createMock(DeliveryExecutionService::class);

        $this->subject = new LtiExtraTimeHandler(
            $this->loggerMock,
            $this->uuidGeneratorMock,
            $this->proctoringAcsServiceMock,
            $this->timerServiceMock,
            $this->ltiCustomSettingsMock,
            $this->batteryDistributionRepositoryMock,
            $this->deliveryExecutionRepositoryMock,
            $this->deliveryExecutionServiceMock,
        );
    }

    public function testAddExtraTimeWithNoExtraTime(): void
    {
        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $deliveryExecution->method('getLtiLaunchParameters')->willReturn(['some_param' => 'value']);

        $this->ltiCustomSettingsMock->expects($this->once())
            ->method('getExtraTime')
            ->willReturn(0);

        $this->loggerMock->expects($this->never())->method('error');

        $this->subject->addExtraTime($deliveryExecution);
    }

    public function testAddExtraTimeToNonBatteryDelivery(): void
    {
        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $deliveryExecution->method('getLtiLaunchParameters')->willReturn(['some_param' => 'value']);
        $deliveryExecution->method('getId')->willReturn('delivery-execution-id');
        $deliveryExecution->method('getUserId')->willReturn('user-id');

        $this->ltiCustomSettingsMock->expects($this->once())
            ->method('getExtraTime')
            ->willReturn(15);

        $this->proctoringAcsServiceMock->expects($this->once())
            ->method('getExtraTime')
            ->with('delivery-execution-id')
            ->willReturn(0);

        $this->timerServiceMock->expects($this->once())
            ->method('getTimer')
            ->with('delivery-execution-id')
            ->willThrowException(new GetTimerException());

        $this->timerServiceMock->expects($this->once())
            ->method('createTimer')
            ->with('delivery-execution-id', $this->callback(function ($timerDefinition) {
                return $timerDefinition instanceof TimerDefinition
                    && $timerDefinition->getExtra()->getMaxTime() === 15 * 60 * 1000
                    && $timerDefinition->getExtra()->getMaxTimeRemaining() === 15 * 60 * 1000
                    && $timerDefinition->getExtra()->getInitialValue() === 15 * 60 * 1000;
            }));

        $this->subject->addExtraTime($deliveryExecution);
    }

    public function testAddExtraTimeToBatteryDeliveryWithTimer(): void
    {
        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $deliveryExecution->method('getLtiLaunchParameters')->willReturn([
            'battery_id' => 'battery-id',
            'user_id' => 'user-id',
        ]);
        $deliveryExecution->method('getId')->willReturn('delivery-execution-id');
        $deliveryExecution->method('getUserId')->willReturn('user-id');
        $deliveryExecution->method('getDeliveryId')->willReturn('delivery-id-2');
        $deliveryExecution->method('doesBelongToBattery')->willReturn(true);

        $batteryDelivery1 = new BatteryDelivery('delivery-id-1', null, order: 1);
        $batteryDelivery2 = new BatteryDelivery('delivery-id-2', null, order: 2);

        $battery = new Battery(
            'battery-id',
            'tenant-id',
            'name',
            'description',
            mode: Battery::MODE_ALL_IN_SEQUENCE,
            deliveries: [$batteryDelivery1, $batteryDelivery2],
        );
        $batteryDistribution = new BatteryDistribution(
            'battery-id',
            'user-id',
            $battery,
        );
        $timer = $this->createMock(TimerDefinition::class);
        $previousTimer = $this->createMock(TimerDefinition::class);

        $previousTimer->expects($this->once())
            ->method('getExtra')
            ->willReturn($this->createMock(TimerDetail::class));

        $this->ltiCustomSettingsMock->expects($this->once())
            ->method('getExtraTime')
            ->willReturn(20);

        $this->batteryDistributionRepositoryMock->expects($this->once())
            ->method('findByBatteryAndUserId')
            ->willReturn($batteryDistribution);

        $this->timerServiceMock->expects($this->once())
            ->method('getTimer')
            ->withConsecutive(
                ['delivery-execution-id'],
                ['previous-delivery-execution-id'],
            )
            ->willReturnOnConsecutiveCalls(
                $timer,
                $previousTimer,
            );

        $this->timerServiceMock->expects($this->once())
            ->method('deleteTimer');

        $this->timerServiceMock->expects($this->once())
            ->method('createTimer');

        $this->deliveryExecutionServiceMock->expects($this->once())
            ->method('createDeliveryExecutionId')
            ->willReturn('previous-delivery-execution-id');

        $this->deliveryExecutionRepositoryMock->expects($this->once())
            ->method('find')
            ->with('previous-delivery-execution-id')
            ->willReturn(new DeliveryExecution(
                'previous-delivery-execution-id',
                'delivery-id-1',
                'tenant-id',
                new DateTime(),
                ['result_id' => 'result-id'],
                null,
                (new DeliveryExecutionExtraStateData())->withExternalTimerData($previousTimer),
            ));

        $this->subject->addExtraTime($deliveryExecution);
    }

    public function testAddExtraTimeHandlesExceptionGracefully(): void
    {
        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $deliveryExecution->method('getLtiLaunchParameters')->willReturn(['some_param' => 'value']);
        $deliveryExecution->method('getId')->willReturn('delivery-execution-id');

        $this->ltiCustomSettingsMock->expects($this->once())
            ->method('getExtraTime')
            ->willReturn(15);

        $this->proctoringAcsServiceMock->expects($this->once())
            ->method('getExtraTime')
            ->willThrowException(new Exception('Test exception'));

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Test exception');

        $this->subject->addExtraTime($deliveryExecution);
    }
}
