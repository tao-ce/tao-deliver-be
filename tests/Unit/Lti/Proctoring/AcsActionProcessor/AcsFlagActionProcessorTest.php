<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Lti\Proctoring\AcsActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Lti\Proctoring\AcsActionProcessor\AcsFlagActionProcessor;
use App\Lti\Service\LtiExtraTimeHandler;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use Exception;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResult;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResultInterface;
use OAT\Library\TaoTimerClient\Service\ProctoringAcsService;
use OAT\Library\TaoTimerClient\Service\TimerServiceInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AcsFlagActionProcessorTest extends KernelTestCase
{
    private AcsFlagActionProcessor $subject;
    private DeliveryExecutionServiceInterface $deliveryExecutionServiceMock;
    private EventDispatcherInterface $eventDispatcherMock;
    private ProctoringAcsService $proctoringAcsServiceMock;

    protected function setUp(): void
    {
        $this->deliveryExecutionServiceMock = $this->createMock(DeliveryExecutionServiceInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->proctoringAcsServiceMock = $this->createMock(ProctoringAcsService::class);

        $this->subject = new AcsFlagActionProcessor(
            $this->eventDispatcherMock,
            $this->proctoringAcsServiceMock,
            $this->createMock(DeliveryExecutionPropertyService::class),
            $this->deliveryExecutionServiceMock,
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
            return [$acsAction, $acsAction === AcsControlInterface::ACTION_FLAG];
        }, AcsControlInterface::SUPPORTED_ACTIONS);
    }

    /**
     * @dataProvider acsControlResultStatusProvider
     */
    public function testProcess(string $deliveryExecutionStatus, string $expectedAcsControlResultStatus): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_FLAG);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('setIsFlagged')
            ->with(true);

        $deliveryExecutionMock
            ->method('getId')
            ->willReturn('deliveryExecutionId');

        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn($deliveryExecutionStatus);

        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('saveDeliveryExecution')
            ->with($deliveryExecutionMock);

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
            new AcsControlResult($expectedAcsControlResultStatus, 15),
            $this->subject->process($acsControlMock, $deliveryExecutionMock),
        );
    }

    public function acsControlResultStatusProvider(): array
    {
        return [
            [DeliveryExecution::STATUS_INTERACTING, AcsControlResultInterface::STATUS_RUNNING],
            [DeliveryExecution::STATUS_SUSPENDED, AcsControlResultInterface::STATUS_PAUSED],
            [DeliveryExecution::STATUS_TERMINATED, AcsControlResultInterface::STATUS_TERMINATED],
            [DeliveryExecution::STATUS_CLOSED, AcsControlResultInterface::STATUS_COMPLETE],
            [DeliveryExecution::STATUS_INITIAL, AcsControlResultInterface::STATUS_NONE],
        ];
    }

    public function testProcessWhenItFails(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_FLAG);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('setIsFlagged')
            ->with(true);

        $deliveryExecutionMock
            ->method('getId')
            ->willReturn('deliveryExecutionId');

        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('saveDeliveryExecution')
            ->with($deliveryExecutionMock);

        $exception = new Exception('reason');
        $this->proctoringAcsServiceMock
            ->expects($this->once())
            ->method('sendAction')
            ->willThrowException($exception);

        $this->expectExceptionObject($exception);

        $this->subject->process($acsControlMock, $deliveryExecutionMock);
    }
}
