<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Lti\Proctoring\AcsActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Lti\Proctoring\AcsActionProcessor\AcsResumeActionProcessor;
use App\Lti\Service\LtiExtraTimeHandler;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Event\TestSessionInteractionEvent;
use App\TestRunner\Factory\AssessmentTestSessionFactory;
use App\TestRunner\Service\TestSessionInitiator;
use App\TestRunner\Service\TimerService;
use Carbon\Carbon;
use Exception;
use OAT\Bundle\QtiBundle\Accessor\TestSessionAccessor;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResult;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResultInterface;
use OAT\Library\TaoTimerClient\Service\ProctoringAcsService;
use OAT\Library\TaoTimerClient\Service\TimerServiceInterface;
use qtism\runtime\tests\AssessmentTestSession;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AcsResumeActionProcessorTest extends KernelTestCase
{
    private AcsResumeActionProcessor $subject;
    private TestSessionInitiator $testSessionInitiatorMock;
    private DeliveryExecutionServiceInterface $deliveryExecutionServiceMock;
    private TestSessionAccessorFactory $testSessionAccessorFactoryMock;
    private TimerService $timerServiceMock;
    private EventDispatcherInterface $eventDispatcherMock;
    private ProctoringAcsService $proctoringAcsServiceMock;

    protected function setUp(): void
    {
        $this->testSessionInitiatorMock = $this->createMock(TestSessionInitiator::class);
        $this->deliveryExecutionServiceMock = $this->createMock(DeliveryExecutionServiceInterface::class);
        $this->testSessionAccessorFactoryMock = $this->createMock(TestSessionAccessorFactory::class);
        $this->timerServiceMock = $this->createMock(TimerService::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->proctoringAcsServiceMock = $this->createMock(ProctoringAcsService::class);

        $assessmentTestSessionFactory = $this->createMock(AssessmentTestSessionFactory::class);
        $assessmentTestSessionFactory->method('createByLtiLaunchParams')
            ->willReturnArgument(0);

        $this->subject = new AcsResumeActionProcessor(
            $this->eventDispatcherMock,
            $this->proctoringAcsServiceMock,
            new DeliveryExecutionPropertyService(
                $this->testSessionAccessorFactoryMock,
                $this->getContainer()->get(LtiCustomSettings::class),
                $assessmentTestSessionFactory,
            ),
            $this->testSessionInitiatorMock,
            $this->deliveryExecutionServiceMock,
            $this->timerServiceMock,
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
            return [$acsAction, $acsAction === AcsControlInterface::ACTION_RESUME];
        }, AcsControlInterface::SUPPORTED_ACTIONS);
    }

    public function testProcess(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_RESUME);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getId')
            ->willReturn('deliveryExecutionId');
        $deliveryExecutionMock
            ->method('getOriginalId')
            ->willReturn('deliveryExecutionId');
        $deliveryExecutionMock
            ->method('getQtiSdkEncodedTestSession')
            ->willReturn('session_data');
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('setStatus')
            ->with(DeliveryExecution::STATUS_INTERACTING);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getFinishedAt')
            ->willReturn(Carbon::now());

        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn(DeliveryExecution::STATUS_INTERACTING);

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock
            ->expects(self::once())
            ->method('isRunning')
            ->willReturn(true);
        $testSessionMock
            ->method('getSessionId')
            ->willReturn('deliveryExecutionId');
        $testSessionMock
            ->expects(self::never())
            ->method('getRoute');

        $testSessionAccessorMock = $this->createMock(TestSessionAccessor::class);
        $testSessionAccessorMock
            ->expects($this->once())
            ->method('retrieve')
            ->with('deliveryExecutionId')
            ->willReturn($testSessionMock);

        $this->testSessionAccessorFactoryMock
            ->expects($this->once())
            ->method('create')
            ->with($deliveryExecutionMock)
            ->willReturn($testSessionAccessorMock);

        $this->timerServiceMock
            ->expects($this->never())
            ->method('beginServerTimer');

        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(TestSessionInteractionEvent::class));

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
            new AcsControlResult(AcsControlResultInterface::STATUS_RUNNING, 15),
            $this->subject->process($acsControlMock, $deliveryExecutionMock),
        );
    }

    public function testProcessWhenItFails(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_RESUME);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getId')
            ->willReturn('deliveryExecutionId');
        $deliveryExecutionMock
            ->method('getOriginalId')
            ->willReturn('deliveryExecutionId');
        $deliveryExecutionMock
            ->method('getQtiSdkEncodedTestSession')
            ->willReturn('session_data');
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getFinishedAt')
            ->willReturn(Carbon::now());

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock
            ->expects(self::once())
            ->method('isRunning')
            ->willReturn(true);

        $testSessionMock
            ->expects(self::never())
            ->method('getRoute');

        $testSessionAccessorMock = $this->createMock(TestSessionAccessor::class);
        $testSessionAccessorMock
            ->expects($this->once())
            ->method('retrieve')
            ->with('deliveryExecutionId')
            ->willReturn($testSessionMock);

        $this->testSessionAccessorFactoryMock
            ->expects($this->once())
            ->method('create')
            ->with($deliveryExecutionMock)
            ->willReturn($testSessionAccessorMock);

        $this->timerServiceMock
            ->expects($this->never())
            ->method('beginServerTimer');

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

    public function testProcessOverFinishedDeliveryExecution(): void
    {
        $this->testSessionInitiatorMock
            ->expects($this->once())
            ->method('startQtiSession');

        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_RESUME);

        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getId')
            ->willReturn('deliveryExecutionId');
        $deliveryExecutionMock
            ->method('getOriginalId')
            ->willReturn('deliveryExecutionId');
        $deliveryExecutionMock
            ->method('getQtiSdkEncodedTestSession')
            ->willReturn('session_data');
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('setStatus')
            ->with(DeliveryExecution::STATUS_INTERACTING);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getFinishedAt')
            ->willReturn(Carbon::now());
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn(DeliveryExecution::STATUS_INTERACTING);
        $deliveryExecutionMock
            ->expects(self::once())
            ->method('reopen');

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock
            ->expects(self::once())
            ->method('isRunning')
            ->willReturn(false);
        $testSessionMock
            ->method('getSessionId')
            ->willReturn('deliveryExecutionId');

        $testSessionAccessorMock = $this->createMock(TestSessionAccessor::class);
        $testSessionAccessorMock
            ->expects($this->once())
            ->method('retrieve')
            ->with('deliveryExecutionId')
            ->willReturn($testSessionMock);
        $this->testSessionAccessorFactoryMock
            ->expects($this->once())
            ->method('create')
            ->with($deliveryExecutionMock)
            ->willReturn($testSessionAccessorMock);

        $this->timerServiceMock
            ->expects($this->once())
            ->method('beginServerTimer')
            ->with($deliveryExecutionMock, $testSessionMock);

        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(TestSessionInteractionEvent::class));

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
            new AcsControlResult(AcsControlResultInterface::STATUS_RUNNING, 15),
            $this->subject->process($acsControlMock, $deliveryExecutionMock),
        );
    }
}
