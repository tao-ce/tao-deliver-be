<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\ActionProcessor\ExitTestActionProcessor;
use App\TestRunner\ActionProcessor\Handler\CantPerformActionException;
use App\TestRunner\Event\DeliveryExecutionClosedEvent;
use App\TestRunner\Event\TestSessionInteractionEvent;
use App\TestRunner\Service\ItemSessionService;
use App\TestRunner\Service\TimerService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Monolog\Logger;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use OAT\Bundle\QtiBundle\Factory\VariableStateFactory;
use PHPUnit\Framework\MockObject\MockObject;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\AssessmentTestSessionState;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ExitTestActionProcessorTest extends KernelTestCase
{
    use DomainTestingTrait;
    use QtiTestingTrait;
    use LoggerTestingTrait;

    private const EXPECTED_ACTION_NAME = 'exitTest';

    private ExitTestActionProcessor $subject;
    private TestSessionAccessorFactory $testSessionAccessorFactory;
    private MockObject|EventDispatcherInterface $eventDispatcherMock;
    private DeliveryExecution $deliveryExecution;
    private TimerService|MockObject $timerServiceMock;

    public function setUp(): void
    {
        self::bootKernel();
        $this->setUpTestLogHandler();

        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->timerServiceMock = $this->createMock(TimerService::class);
        $this->testSessionAccessorFactory = self::getContainer()->get(TestSessionAccessorFactory::class);
        $this->subject = new ExitTestActionProcessor(
            $this->eventDispatcherMock,
            self::getContainer()->get(DeliveryExecutionPropertyService::class),
            new ItemSessionService(
                self::getContainer()->get(VariableStateFactory::class),
                self::getContainer()->get(DeliveryExecutionPropertyService::class),
                $this->timerServiceMock,
            ),
            static::getContainer()->get('monolog.logger.audit_delivery_execution'),
        );

        $this->copyCompiledTestToStorage();

        $this->deliveryExecution = $this->createTestDeliveryExecution(
            'userId#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            [],
        );
    }

    public function testItExitTestAndSaveResponses(): void
    {
        $accessor = $this->testSessionAccessorFactory->create($this->deliveryExecution);
        $testSession = $accessor->instantiate();

        $this->eventDispatcherMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [$this->isInstanceOf(DeliveryExecutionClosedEvent::class)],
                [$this->isInstanceOf(TestSessionInteractionEvent::class)],
            );


        $this->timerServiceMock
            ->expects($this->once())
            ->method('endServerTimer')
            ->with(
                $this->isInstanceOf(DeliveryExecution::class),
                $this->isInstanceOf(AssessmentTestSession::class),
                12.34,
            );

        $actionParameters = [
            'name' => 'exitTest',
            'id' => 'exitTest_12345',
            'parameters' => [
                'itemResponse' => '{"RESPONSE":{"base":{"identifier":"test"}}}',
                'itemDuration' => '12.34',
            ],
        ];

        $testSession->beginTestSession();
        $testSession->beginAttempt();
        $accessor->persist($testSession);

        $result = $this->subject->process($this->deliveryExecution, $actionParameters);
        $updatedTestSession = $accessor->retrieve($this->deliveryExecution->getId());

        $this->assertEquals(DeliveryExecution::STATUS_CLOSED, $this->deliveryExecution->getStatus());
        $this->assertEquals(AssessmentTestSessionState::CLOSED, $updatedTestSession->getState());
        $this->assertEquals(
            [
                'success' => true,
                'name' => 'exitTest',
                'id' => 'exitTest_12345',
                'errorCode' => null,
                'errorMessage' => null,
                'values' => [],
            ],
            $result,
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#Basic#resultId#tenantId] - test taker has ended the Test: Test-T01',
            Logger::INFO,
            'audit_delivery_execution',
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#Basic#resultId#tenantId] - test taker responses were saved for Test: Test-T01',
            Logger::INFO,
            'audit_delivery_execution',
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#Basic#resultId#tenantId] - test taker test was saved with the status closed for Test: Test-T01',
            Logger::INFO,
            'audit_delivery_execution',
        );
    }

    public function testSuccessAccessibilityValidation(): void
    {
        $this->subject->validateAvailability(DeliveryExecution::STATUS_INTERACTING);
        self::assertTrue(true);
    }

    public function testFailedAccessibilityValidationForUnavailableStatus(): void
    {
        try {
            $this->subject->validateAvailability(DeliveryExecution::STATUS_SUSPENDED);
        } catch (CantPerformActionException $e) {
            $this->assertEquals(
                sprintf(
                    'Can\'t perform the action "%s" because the test session is suspended',
                    self::EXPECTED_ACTION_NAME,
                ),
                $e->getMessage(),
            );
        }
        try {
            $this->subject->validateAvailability(DeliveryExecution::STATUS_TERMINATED);
        } catch (CantPerformActionException $e) {
            $this->assertEquals(
                sprintf(
                    'Can\'t perform the action "%s" because the test session is terminated',
                    self::EXPECTED_ACTION_NAME,
                ),
                $e->getMessage(),
            );
        }
        try {
            $this->subject->validateAvailability(DeliveryExecution::STATUS_CLOSED);
        } catch (CantPerformActionException $e) {
            $this->assertEquals(
                sprintf(
                    'Can\'t perform the action "%s" because the test session is closed',
                    self::EXPECTED_ACTION_NAME,
                ),
                $e->getMessage(),
            );
        }
        try {
            $this->subject->validateAvailability(DeliveryExecution::STATUS_INITIAL);
        } catch (CantPerformActionException $e) {
            $this->assertEquals(
                sprintf(
                    'Can\'t perform the action "%s" because the test session in unavailable status "initial"',
                    self::EXPECTED_ACTION_NAME,
                ),
                $e->getMessage(),
            );
        }
    }
}
