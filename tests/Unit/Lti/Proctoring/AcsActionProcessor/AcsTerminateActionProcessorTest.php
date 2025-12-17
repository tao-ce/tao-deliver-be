<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Lti\Proctoring\AcsActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Lti\Proctoring\AcsActionProcessor\AcsTerminateActionProcessor;
use App\Lti\Service\LtiExtraTimeHandler;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Service\TestSessionShutdownService;
use App\TestRunner\Service\TimerService;
use Exception;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResult;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResultInterface;
use OAT\Library\TaoTimerClient\Service\ProctoringAcsService;
use OAT\Library\TaoTimerClient\Service\TimerServiceInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AcsTerminateActionProcessorTest extends KernelTestCase
{
    private AcsTerminateActionProcessor $subject;
    private ProctoringAcsService $proctoringAcsServiceMock;

    protected function setUp(): void
    {
        $this->proctoringAcsServiceMock = $this->createMock(ProctoringAcsService::class);
        $this->subject = new AcsTerminateActionProcessor(
            $this->createMock(EventDispatcherInterface::class),
            $this->proctoringAcsServiceMock,
            $this->createMock(DeliveryExecutionPropertyService::class),
            $this->createMock(DeliveryExecutionServiceInterface::class),
            $this->createMock(TimerService::class),
            $this->createMock(TestSessionShutdownService::class),
            $this->getContainer()->get(LtiCustomSettings::class),
            self::getContainer()->get(LtiExtraTimeHandler::class),
            self::getContainer()->get(TimerServiceInterface::class),
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
            return [$acsAction, $acsAction === AcsControlInterface::ACTION_TERMINATE];
        }, AcsControlInterface::SUPPORTED_ACTIONS);
    }

    public function testProcess(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_TERMINATE);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getId')
            ->willReturn('deliveryExecutionId');

        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn(DeliveryExecution::STATUS_TERMINATED);

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
            new AcsControlResult(AcsControlResultInterface::STATUS_TERMINATED, 15),
            $this->subject->process($acsControlMock, $deliveryExecutionMock),
        );
    }

    public function testProcessWhenItFails(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_TERMINATE);

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
