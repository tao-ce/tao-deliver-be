<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Lti\Proctoring\AcsActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Lti\Proctoring\AcsActionProcessor\AcsUpdateActionProcessor;
use App\Lti\Service\LtiExtraTimeHandler;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use Exception;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResult;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResultInterface;
use OAT\Library\TaoTimerClient\Model\Contract\TimerDefinitionInterface;
use OAT\Library\TaoTimerClient\Model\TimerDetail;
use OAT\Library\TaoTimerClient\Service\Exception\ProctoringAcsSendActionFailedException;
use OAT\Library\TaoTimerClient\Service\ProctoringAcsService;
use OAT\Library\TaoTimerClient\Service\TimerServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AcsUpdateActionProcessorTest extends KernelTestCase
{
    private AcsUpdateActionProcessor $subject;
    private ProctoringAcsService $proctoringAcsServiceMock;
    private LtiExtraTimeHandler|MockObject $ltiExtraTimeHandlerMock;
    private TimerServiceInterface|MockObject $timerServiceMock;

    protected function setUp(): void
    {
        $this->proctoringAcsServiceMock = $this->createMock(ProctoringAcsService::class);
        $this->ltiExtraTimeHandlerMock = $this->createMock(LtiExtraTimeHandler::class);
        $this->timerServiceMock = $this->createMock(TimerServiceInterface::class);

        $this->subject = new AcsUpdateActionProcessor(
            $this->createMock(EventDispatcherInterface::class),
            $this->proctoringAcsServiceMock,
            $this->createMock(DeliveryExecutionPropertyService::class),
            $this->getContainer()->get(LtiCustomSettings::class),
            $this->ltiExtraTimeHandlerMock,
            $this->timerServiceMock,
        );
    }

    /**
     * @dataProvider acsActionProvider
     */
    public function testSupports(string $acsAction, bool $isSupported): void
    {
        $acsControl = $this->createMock(AcsControlInterface::class);
        $acsControl
            ->expects($this->once())
            ->method('getAction')
            ->willReturn($acsAction);

        $this->assertSame($isSupported, $this->subject->supports($acsControl));
    }

    public function acsActionProvider(): array
    {
        return array_map(static function (string $acsAction) {
            return [$acsAction, $acsAction === AcsControlInterface::ACTION_UPDATE];
        }, AcsControlInterface::SUPPORTED_ACTIONS);
    }

    public function testProcess(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_UPDATE);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getId')
            ->willReturn('deliveryExecutionId');

        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn(DeliveryExecution::STATUS_SUSPENDED);

        $this->proctoringAcsServiceMock
            ->expects($this->once())
            ->method('sendAction')
            ->with('deliveryExecutionId', $acsControlMock);

        $this->proctoringAcsServiceMock
            ->expects($this->once())
            ->method('getExtraTime')
            ->with('deliveryExecutionId')
            ->willReturn(15);

        $this->assertEquals(
            new AcsControlResult(AcsControlResultInterface::STATUS_PAUSED, 15),
            $this->subject->process($acsControlMock, $deliveryExecutionMock),
        );
    }

    public function testProcessExtraTimeReset(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_UPDATE);
        $acsControlMock
            ->method('getExtraTime')
            ->willReturn(0);
        $acsControlMock
            ->expects($this->never())
            ->method('setExtraTime');

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getId')
            ->willReturn('deliveryExecutionId');

        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn(DeliveryExecution::STATUS_SUSPENDED);

        $this->proctoringAcsServiceMock
            ->expects($this->once())
            ->method('sendAction')
            ->with('deliveryExecutionId', $acsControlMock);

        $this->proctoringAcsServiceMock
            ->expects($this->once())
            ->method('getExtraTime')
            ->with('deliveryExecutionId')
            ->willReturn(0);

        $this->assertEquals(
            new AcsControlResult(AcsControlResultInterface::STATUS_PAUSED, 0),
            $this->subject->process($acsControlMock, $deliveryExecutionMock),
        );
    }

    public function testProcessExtraTimeResetWithBaselineExtraTime(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_UPDATE);
        $acsControlMock
            ->method('getExtraTime')
            ->willReturn(0);
        $acsControlMock
            ->expects($this->once())
            ->method('setExtraTime')
            ->with(10);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getId')
            ->willReturn('deliveryExecutionId');
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn(DeliveryExecution::STATUS_SUSPENDED);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn(['custom' => [LtiCustomSettings::PARAM_EXTRA_TIME => 10]]);

        $this->proctoringAcsServiceMock
            ->expects($this->once())
            ->method('sendAction')
            ->with('deliveryExecutionId', $acsControlMock);

        $this->proctoringAcsServiceMock
            ->expects($this->once())
            ->method('getExtraTime')
            ->with('deliveryExecutionId')
            ->willReturn(10);

        $this->assertEquals(
            new AcsControlResult(AcsControlResultInterface::STATUS_PAUSED, 10),
            $this->subject->process($acsControlMock, $deliveryExecutionMock),
        );
    }

    public function testProcessExtraTimeResetWithBaselineExtraTimeForBattery(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_UPDATE);
        $acsControlMock
            ->method('getExtraTime')
            ->willReturn(0);
        $acsControlMock
            ->expects($this->once())
            ->method('setExtraTime')
            ->with(1);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getId')
            ->willReturn('deliveryExecutionId');
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn(DeliveryExecution::STATUS_SUSPENDED);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn(['custom' => [LtiCustomSettings::PARAM_EXTRA_TIME => 10]]);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('doesBelongToBattery')
            ->willReturn(true);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getDeliveryId')
            ->willReturn('deliveryId');

        $timerDetailMock = $this->createMock(TimerDetail::class);
        $timerDetailMock
            ->expects($this->once())
            ->method('getInitialValue')
            ->willReturn(5678);

        $timerMock = $this->createMock(TimerDefinitionInterface::class);
        $timerMock
            ->expects($this->once())
            ->method('getExtra')
            ->willReturn($timerDetailMock);

        $this->ltiExtraTimeHandlerMock
            ->expects($this->once())
            ->method('getPreviousDeliveryExecutionTimer')
            ->with($deliveryExecutionMock, 'deliveryId')
            ->willReturn($timerMock);

        $this->proctoringAcsServiceMock
            ->expects($this->once())
            ->method('sendAction')
            ->with('deliveryExecutionId', $acsControlMock);

        $this->proctoringAcsServiceMock
            ->expects($this->once())
            ->method('getExtraTime')
            ->with('deliveryExecutionId')
            ->willReturn(10);

        $this->assertEquals(
            new AcsControlResult(AcsControlResultInterface::STATUS_PAUSED, 10),
            $this->subject->process($acsControlMock, $deliveryExecutionMock),
        );
    }

    public function testProcessExtraTimeDecreaseWithBaselineExtraTime(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_UPDATE);
        $acsControlMock
            ->method('getExtraTime')
            ->willReturn(9);
        $acsControlMock
            ->expects($this->once())
            ->method('setExtraTime')
            ->with(10);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getId')
            ->willReturn('deliveryExecutionId');
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn(DeliveryExecution::STATUS_SUSPENDED);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getLtiLaunchParameters')
            ->willReturn(['custom' => [LtiCustomSettings::PARAM_EXTRA_TIME => 10]]);

        $this->proctoringAcsServiceMock
            ->expects($this->once())
            ->method('sendAction')
            ->with('deliveryExecutionId', $acsControlMock);

        $this->proctoringAcsServiceMock
            ->expects($this->once())
            ->method('getExtraTime')
            ->with('deliveryExecutionId')
            ->willReturn(10);

        $this->assertEquals(
            new AcsControlResult(AcsControlResultInterface::STATUS_PAUSED, 10),
            $this->subject->process($acsControlMock, $deliveryExecutionMock),
        );
    }

    public function testProcessWhenItFails(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_UPDATE);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getId')
            ->willReturn('deliveryExecutionId');

        $exception = new Exception('reason');
        $this->proctoringAcsServiceMock
            ->expects($this->once())
            ->method('sendAction')
            ->willThrowException($exception);

        $this->expectExceptionObject($exception);

        $this->subject->process($acsControlMock, $deliveryExecutionMock);
    }
}
