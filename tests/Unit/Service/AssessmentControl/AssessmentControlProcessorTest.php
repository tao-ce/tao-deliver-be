<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\AssessmentControl;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\Event\AcsControlProcessedEvent;
use App\Lti\Proctoring\AcsActionProcessor\AcsActionProcessorInterface;
use App\Service\AssessmentControl\AssessmentControlProcessor;
use App\Service\AssessmentControl\Exception\NotControllableDeliveryExecutionException;
use App\Service\AssessmentControl\Exception\NotSupportedAssessmentControlAction;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResultInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

class AssessmentControlProcessorTest extends TestCase
{
    private const EXPECTED_ACS_RESULT_STATUS = 'running';

    private AssessmentControlProcessor $subject;
    private LoggerInterface|MockObject $loggerMock;
    private AcsActionProcessorInterface|MockObject $acsActionProcessorMockOne;
    private AcsActionProcessorInterface|MockObject $acsActionProcessorMockTwo;
    private EventDispatcherInterface|MockObject $eventDispatcherMock;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->acsActionProcessorMockOne = $this->createMock(AcsActionProcessorInterface::class);
        $this->acsActionProcessorMockTwo = $this->createMock(AcsActionProcessorInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->subject = new AssessmentControlProcessor(
            $this->loggerMock,
            $this->eventDispatcherMock,
            [
                $this->acsActionProcessorMockOne,
                $this->acsActionProcessorMockTwo,
            ],
        );
    }

    /**
     * @dataProvider acsActionProvider
     */
    public function testProcess(string $acsAction): void
    {
        $acsControlResultMock = $this->createMock(AcsControlResultInterface::class);
        $acsControlResultMock
            ->expects(self::exactly(1))
            ->method('getStatus')
            ->willReturn(self::EXPECTED_ACS_RESULT_STATUS);

        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn($acsAction);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('isStateInitial')
            ->willReturn(false);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('isStateFinal')
            ->willReturn(false);

        $deliveryExecutionMock
            ->method('getId')
            ->willReturn('deliveryExecutionId');

        $this->acsActionProcessorMockOne
            ->expects($this->once())
            ->method('supports')
            ->with($acsControlMock)
            ->willReturn(false);

        $this->acsActionProcessorMockTwo
            ->expects($this->once())
            ->method('supports')
            ->with($acsControlMock)
            ->willReturn(true);

        $this->acsActionProcessorMockTwo
            ->expects($this->once())
            ->method('process')
            ->with($acsControlMock, $deliveryExecutionMock)
            ->willReturn($acsControlResultMock);

        $this->eventDispatcherMock
            ->expects(self::exactly(1))
            ->method('dispatch')
            ->with(new AcsControlProcessedEvent(
                $deliveryExecutionMock,
                self::EXPECTED_ACS_RESULT_STATUS,
                $acsControlMock,
            ));

        $this->loggerMock
            ->method('info')
            ->withConsecutive(
                [sprintf('[deliveryExecutionId] Processing "%s" ACS action...', $acsAction)],
                [sprintf('[deliveryExecutionId] "%s" ACS action has been successfully processed', $acsAction)],
            );

        $this->assertSame($acsControlResultMock, $this->subject->__invoke($deliveryExecutionMock, $acsControlMock));
    }

    public function acsActionProvider(): array
    {
        return array_map(static function (string $acsAction) {
            return [$acsAction];
        }, AcsControlInterface::SUPPORTED_ACTIONS);
    }

    public function testProcessWithUnsupportedAcsAction(): void
    {
        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $this->eventDispatcherMock
            ->expects(self::never())
            ->method('dispatch');

        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->expects($this->exactly(1))
            ->method('getAction')
            ->willReturn('unsupported-action');

        $this->expectException(NotSupportedAssessmentControlAction::class);
        $this->expectExceptionMessage('"unsupported-action" ACS action is not supported');

        $this->subject->__invoke($deliveryExecutionMock, $acsControlMock);
    }

    public function testProcessWhenDeliveryExecutionIsClosed(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->expects($this->exactly(1))
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_UPDATE);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('isStateFinal')
            ->willReturn(true);

        $this->eventDispatcherMock
            ->expects(self::never())
            ->method('dispatch');

        $this->expectException(NotControllableDeliveryExecutionException::class);
        $this->expectExceptionMessage('Delivery execution\'s state does not permit this action');

        $this->subject->__invoke($deliveryExecutionMock, $acsControlMock);
    }

    public function testProcessWhenDeliveryExecutionIsNotInitialized(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->expects($this->exactly(1))
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_UPDATE);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('isStateInitial')
            ->willReturn(true);

        $this->eventDispatcherMock
            ->expects(self::never())
            ->method('dispatch');

        $this->expectException(NotControllableDeliveryExecutionException::class);
        $this->expectExceptionMessage('Delivery execution\'s state does not permit this action');

        $this->subject->__invoke($deliveryExecutionMock, $acsControlMock);
    }

    public function testProcessResumeWhenDeliveryExecutionIsClosed(): void
    {
        $acsControlResultMock = $this->createMock(AcsControlResultInterface::class);
        $acsControlResultMock
            ->expects(self::exactly(1))
            ->method('getStatus')
            ->willReturn(self::EXPECTED_ACS_RESULT_STATUS);

        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_RESUME);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('isStateFinal')
            ->willReturn(true);

        $this->acsActionProcessorMockOne
            ->expects(self::exactly(1))
            ->method('supports')
            ->with($acsControlMock)
            ->willReturn(true);
        $this->acsActionProcessorMockOne
            ->expects($this->once())
            ->method('process')
            ->with($acsControlMock, $deliveryExecutionMock)
            ->willReturn($acsControlResultMock);

        $this->eventDispatcherMock
            ->expects(self::exactly(1))
            ->method('dispatch')
            ->with(new AcsControlProcessedEvent(
                $deliveryExecutionMock,
                self::EXPECTED_ACS_RESULT_STATUS,
                $acsControlMock,
            ));

        $this->assertSame($acsControlResultMock, $this->subject->__invoke($deliveryExecutionMock, $acsControlMock));
    }

    public function testProcessWithoutSupportedProcessors(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_RESUME);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('isStateInitial')
            ->willReturn(false);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('isStateFinal')
            ->willReturn(false);

        $this->acsActionProcessorMockOne
            ->expects($this->once())
            ->method('supports')
            ->with($acsControlMock)
            ->willReturn(false);

        $this->acsActionProcessorMockTwo
            ->expects($this->once())
            ->method('supports')
            ->with($acsControlMock)
            ->willReturn(false);

        $this->eventDispatcherMock
            ->expects(self::never())
            ->method('dispatch');

        $this->expectException(NotSupportedAssessmentControlAction::class);
        $this->expectExceptionMessage('"resume" ACS action is not supported');

        $this->subject->__invoke($deliveryExecutionMock, $acsControlMock);
    }

    public function testProcessFlagWhenDeliveryExecutionIsClosed(): void
    {
        $acsControlResultMock = $this->createMock(AcsControlResultInterface::class);
        $acsControlResultMock
            ->expects(self::exactly(1))
            ->method('getStatus')
            ->willReturn(self::EXPECTED_ACS_RESULT_STATUS);

        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_FLAG);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('isStateFinal')
            ->willReturn(true);

        $this->acsActionProcessorMockOne
            ->expects(self::exactly(1))
            ->method('supports')
            ->with($acsControlMock)
            ->willReturn(true);
        $this->acsActionProcessorMockOne
            ->expects($this->once())
            ->method('process')
            ->with($acsControlMock, $deliveryExecutionMock)
            ->willReturn($acsControlResultMock);

        $this->eventDispatcherMock
            ->expects(self::exactly(1))
            ->method('dispatch')
            ->with(new AcsControlProcessedEvent(
                $deliveryExecutionMock,
                self::EXPECTED_ACS_RESULT_STATUS,
                $acsControlMock,
            ));

        $this->assertSame($acsControlResultMock, $this->subject->__invoke($deliveryExecutionMock, $acsControlMock));
    }
}
