<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionStatus;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Service\ItemSessionService;
use App\TestRunner\Service\TestSessionShutdownService;
use App\TestRunner\Service\TestSessionStateCollisionException;
use App\TestRunner\Service\TimerService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use OAT\Bundle\QtiBundle\Factory\VariableStateFactory;
use Psr\Log\LoggerInterface;
use qtism\data\AssessmentItemRef;
use qtism\runtime\tests\AssessmentItemSession;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\AssessmentTestSessionException;
use qtism\runtime\tests\AssessmentTestSessionState;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class TestSessionShutdownServiceTest extends KernelTestCase
{
    use QtiTestingTrait;
    use DomainTestingTrait;

    private TestSessionShutdownService $subject;
    private DeliveryExecution $deliveryExecution;

    public function setUp(): void
    {
        static::bootKernel();
    }

    public function testProduceExceptionIfNoChanges()
    {
        $this->subject = static::getContainer()->get(TestSessionShutdownService::class);
        $this->createTestSession();
        $this->expectException(TestSessionStateCollisionException::class);
        $this->subject->endTestSession($this->deliveryExecution, DeliveryExecutionStatus::STATUS_TERMINATED);
    }

    public function testFreeTemporaryStateOnTerminateAction(): void
    {
        $timerServiceMock = $this->createMock(TimerService::class);
        $timerServiceMock->expects($this->once())->method('endServerTimer');
        $itemSessionService = new ItemSessionService(
            static::getContainer()->get(VariableStateFactory::class),
            static::getContainer()->get(DeliveryExecutionPropertyService::class),
            $timerServiceMock,
        );

        $itemSessionMock = $this->createMock(AssessmentItemSession::class);
        $itemSessionMock->method('getState')->willReturn(AssessmentTestSessionState::SUSPENDED);

        $assessmentItemRefMock = $this->createMock(AssessmentItemRef::class);
        $assessmentItemRefMock->method('getIdentifier')->willReturn('Q01');

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock->method('isRunning')->willReturn(true);
        $testSessionMock->method('getCurrentAssessmentItemRef')->willReturn($assessmentItemRefMock);
        $testSessionMock->method('getCurrentAssessmentItemSession')->willReturn($itemSessionMock);
        $testSessionMock->expects($this->once())->method('endTestSession');

        $deliveryExecutionPropertyServiceMock = $this->createMock(DeliveryExecutionPropertyService::class);
        $deliveryExecutionPropertyServiceMock
            ->expects($this->once())
            ->method('fetchTestSession')
            ->willReturn($testSessionMock);
        $deliveryExecutionPropertyServiceMock
            ->expects($this->once())
            ->method('persistTestSession')
            ->with($testSessionMock);


        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->subject = new TestSessionShutdownService(
            $deliveryExecutionPropertyServiceMock,
            $itemSessionService,
            static::getContainer()->get(LoggerInterface::class),
            static::getContainer()->get(RepositoryAwareDeliveryExecutionServiceInterface::class),
            $eventDispatcherMock,
        );

        $this->createTestSession(
            status: DeliveryExecutionStatus::STATUS_INTERACTING->value,
        );
        $this->subject->endTestSession($this->deliveryExecution, DeliveryExecutionStatus::STATUS_TERMINATED);
    }

    public function testSkipItemSubmitOnTerminationIfSessionIsNotRunning(): void
    {
        $timerServiceMock = $this->createMock(TimerService::class);
        $timerServiceMock->expects($this->never())->method('endServerTimer');
        $itemSessionService = new ItemSessionService(
            static::getContainer()->get(VariableStateFactory::class),
            static::getContainer()->get(DeliveryExecutionPropertyService::class),
            $timerServiceMock,
        );

        $itemSessionMock = $this->createMock(AssessmentItemSession::class);
        $itemSessionMock->method('getState')->willReturn(AssessmentTestSessionState::INTERACTING);

        $assessmentItemRefMock = $this->createMock(AssessmentItemRef::class);
        $assessmentItemRefMock->method('getIdentifier')->willReturn('Q01');

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock->method('isRunning')->willReturn(false);
        $testSessionMock->expects($this->never())->method('getCurrentAssessmentItemRef');
        $testSessionMock->expects($this->never())->method('getCurrentAssessmentItemSession');
        $testSessionMock->expects($this->never())->method('endTestSession');
        $testSessionMock->expects($this->never())->method('endAttempt');

        $deliveryExecutionPropertyServiceMock = $this->createMock(DeliveryExecutionPropertyService::class);
        $deliveryExecutionPropertyServiceMock
            ->expects($this->once())
            ->method('fetchTestSession')
            ->willReturn($testSessionMock);
        $deliveryExecutionPropertyServiceMock
            ->expects($this->never())
            ->method('persistTestSession');

        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->subject = new TestSessionShutdownService(
            $deliveryExecutionPropertyServiceMock,
            $itemSessionService,
            static::getContainer()->get(LoggerInterface::class),
            static::getContainer()->get(RepositoryAwareDeliveryExecutionServiceInterface::class),
            $eventDispatcherMock,
        );

        $this->createTestSession(
            status: DeliveryExecutionStatus::STATUS_INTERACTING->value,
        );
        $this->subject->endTestSession($this->deliveryExecution, DeliveryExecutionStatus::STATUS_TERMINATED);
    }

    public function testSubmitItemOnTerminateAction(): void
    {
        $timerServiceMock = $this->createMock(TimerService::class);
        $timerServiceMock->expects($this->once())->method('endServerTimer');
        $itemSessionService = new ItemSessionService(
            static::getContainer()->get(VariableStateFactory::class),
            static::getContainer()->get(DeliveryExecutionPropertyService::class),
            $timerServiceMock,
        );

        $itemSessionMock = $this->createMock(AssessmentItemSession::class);
        $itemSessionMock->method('getState')->willReturn(AssessmentTestSessionState::INTERACTING);

        $assessmentItemRefMock = $this->createMock(AssessmentItemRef::class);
        $assessmentItemRefMock->method('getIdentifier')->willReturn('Q01');

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock->method('isRunning')->willReturn(true);
        $testSessionMock->method('getCurrentAssessmentItemRef')->willReturn($assessmentItemRefMock);
        $testSessionMock->method('getCurrentAssessmentItemSession')->willReturn($itemSessionMock);
        $testSessionMock->expects($this->once())->method('endTestSession');
        $testSessionMock->expects($this->once())->method('endAttempt');

        $deliveryExecutionPropertyServiceMock = $this->createMock(DeliveryExecutionPropertyService::class);
        $deliveryExecutionPropertyServiceMock
            ->expects($this->once())
            ->method('fetchTestSession')
            ->willReturn($testSessionMock);
        $deliveryExecutionPropertyServiceMock
            ->expects($this->once())
            ->method('persistTestSession')
            ->with($testSessionMock);

        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->subject = new TestSessionShutdownService(
            $deliveryExecutionPropertyServiceMock,
            $itemSessionService,
            static::getContainer()->get(LoggerInterface::class),
            static::getContainer()->get(RepositoryAwareDeliveryExecutionServiceInterface::class),
            $eventDispatcherMock,
        );

        $this->createTestSession(
            status: DeliveryExecutionStatus::STATUS_INTERACTING->value,
        );
        $this->subject->endTestSession($this->deliveryExecution, DeliveryExecutionStatus::STATUS_TERMINATED);
    }

    public function testItemSubmissionFailsOnTerminateAction(): void
    {
        $timerServiceMock = $this->createMock(TimerService::class);
        $timerServiceMock->expects($this->once())->method('endServerTimer');
        $itemSessionService = new ItemSessionService(
            static::getContainer()->get(VariableStateFactory::class),
            static::getContainer()->get(DeliveryExecutionPropertyService::class),
            $timerServiceMock,
        );

        $itemSessionMock = $this->createMock(AssessmentItemSession::class);
        $itemSessionMock->method('getState')->willReturn(AssessmentTestSessionState::INTERACTING);

        $assessmentItemRefMock = $this->createMock(AssessmentItemRef::class);
        $assessmentItemRefMock->method('getIdentifier')->willReturn('Q01');

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock->method('isRunning')->willReturn(true);
        $testSessionMock->method('getCurrentAssessmentItemRef')->willReturn($assessmentItemRefMock);
        $testSessionMock->method('getCurrentAssessmentItemSession')->willReturn($itemSessionMock);
        $testSessionMock->expects($this->once())->method('endTestSession');
        $testSessionMock->expects($this->once())->method('endAttempt')->willThrowException(
            new AssessmentTestSessionException('', AssessmentTestSessionException::ASSESSMENT_ITEM_SKIPPING_FORBIDDEN),
        );

        $deliveryExecutionPropertyServiceMock = $this->createMock(DeliveryExecutionPropertyService::class);
        $deliveryExecutionPropertyServiceMock
            ->expects($this->once())
            ->method('fetchTestSession')
            ->willReturn($testSessionMock);
        $deliveryExecutionPropertyServiceMock
            ->expects($this->once())
            ->method('persistTestSession')
            ->with($testSessionMock);

        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->subject = new TestSessionShutdownService(
            $deliveryExecutionPropertyServiceMock,
            $itemSessionService,
            static::getContainer()->get(LoggerInterface::class),
            static::getContainer()->get(RepositoryAwareDeliveryExecutionServiceInterface::class),
            $eventDispatcherMock,
        );

        $this->createTestSession(
            status: DeliveryExecutionStatus::STATUS_INTERACTING->value,
        );
        $this->subject->endTestSession($this->deliveryExecution, DeliveryExecutionStatus::STATUS_TERMINATED);
    }

    private function createTestSession(string $packageName = 'BasicTestFullTimerStack', ?string $status = null): void
    {
        $this->deliveryExecution = $this->createTestDeliveryExecution(
            "userId#$packageName#resultId#tenantId",
            $packageName,
            'tenantId',
            ['ltiLaunchParameters'],
            null,
            status: $status ?? DeliveryExecutionStatus::STATUS_TERMINATED->value,
        );
        $this->deliveryExecution->addTemporaryItemState('Q01', json_encode(
            [
                'RESPONSE' => [
                    'validity' => true,
                    'response' => [
                        'base' => [
                            'identifier' => 'tao',
                        ],
                    ],
                ],
            ],
        ));

        $this->copyCompiledTestToStorage([
            'compact-test.xml',
            'Q01/item.json',
            'Q02/item.json',
            'Q03/item.json',
        ], $packageName);

        /** @var DeliveryExecutionPropertyService $deliveryExecutionPropertyService */
        $deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);

        $testSession = $deliveryExecutionPropertyService->fetchTestSession($this->deliveryExecution);

        $testSession->beginTestSession();
        $testSession->beginAttempt();

        $deliveryExecutionPropertyService->persistTestSession($testSession);
    }
}
