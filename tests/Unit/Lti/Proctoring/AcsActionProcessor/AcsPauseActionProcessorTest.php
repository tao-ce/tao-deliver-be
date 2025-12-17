<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Lti\Proctoring\AcsActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Lti\Proctoring\AcsActionProcessor\AcsPauseActionProcessor;
use App\Lti\Service\LtiExtraTimeHandler;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Factory\AssessmentTestSessionFactory;
use App\TestRunner\Service\TimerService;
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

class AcsPauseActionProcessorTest extends KernelTestCase
{
    private AcsPauseActionProcessor $subject;
    private DeliveryExecutionServiceInterface $deliveryExecutionServiceMock;
    private TestSessionAccessorFactory $testSessionAccessorFactoryMock;
    private TimerService $timerServiceMock;
    private EventDispatcherInterface $eventDispatcherMock;
    private ProctoringAcsService $proctoringAcsServiceMock;

    protected function setUp(): void
    {
        $this->deliveryExecutionServiceMock = $this->createMock(DeliveryExecutionServiceInterface::class);
        $this->testSessionAccessorFactoryMock = $this->createMock(TestSessionAccessorFactory::class);
        $this->timerServiceMock = $this->createMock(TimerService::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->proctoringAcsServiceMock = $this->createMock(ProctoringAcsService::class);

        $assessmentTestSessionFactory = $this->createMock(AssessmentTestSessionFactory::class);
        $assessmentTestSessionFactory->method('createByLtiLaunchParams')
            ->willReturnArgument(0);

        $this->subject = new AcsPauseActionProcessor(
            $this->eventDispatcherMock,
            $this->proctoringAcsServiceMock,
            new DeliveryExecutionPropertyService(
                $this->testSessionAccessorFactoryMock,
                $this->getContainer()->get(LtiCustomSettings::class),
                $assessmentTestSessionFactory,
            ),
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
            return [$acsAction, $acsAction === AcsControlInterface::ACTION_PAUSE];
        }, AcsControlInterface::SUPPORTED_ACTIONS);
    }

    public function testProcess(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_PAUSE);

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
            ->with(DeliveryExecution::STATUS_SUSPENDED);

        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getStatus')
            ->willReturn(DeliveryExecution::STATUS_SUSPENDED);

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock->method('getSessionId')->willReturn('deliveryExecutionId');

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
            ->method('endServerTimer')
            ->with($deliveryExecutionMock, $testSessionMock, 0.0);

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
            new AcsControlResult(AcsControlResultInterface::STATUS_PAUSED, 15),
            $this->subject->process($acsControlMock, $deliveryExecutionMock),
        );
    }

    public function testProcessWhenItFails(): void
    {
        $acsControlMock = $this->createMock(AcsControlInterface::class);
        $acsControlMock
            ->method('getAction')
            ->willReturn(AcsControlInterface::ACTION_PAUSE);

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

        $testSessionMock = $this->createMock(AssessmentTestSession::class);

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
            ->method('endServerTimer')
            ->with($deliveryExecutionMock, $testSessionMock, 0.0);

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
