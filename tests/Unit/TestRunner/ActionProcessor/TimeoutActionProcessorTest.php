<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\ActionProcessor\ActionProcessorInterface;
use App\TestRunner\ActionProcessor\Handler\CantPerformActionException;
use App\TestRunner\ActionProcessor\TimeoutActionProcessor;
use App\TestRunner\Generator\TestContextGenerator;
use App\TestRunner\Service\ItemSessionService;
use App\TestRunner\Service\TestSessionNavigator;
use App\TestRunner\Service\TimerService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TimeoutActionProcessorTest extends KernelTestCase
{
    use DomainTestingTrait;
    use QtiTestingTrait;
    use LoggerTestingTrait;
    use MessengerTestingTrait;

    private const EXPECTED_ACTION_NAME = 'timeout';

    private TimeoutActionProcessor $subject;
    private DeliveryExecution $deliveryExecution;
    private DeliveryExecutionPropertyService $deliveryExecutionPropertyService;

    public function setUp(): void
    {
        static::bootKernel();

        $this->setUpTestLogHandler();

        $testContextGenerator = static::getContainer()->get(TestContextGenerator::class);
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);
        $this->subject = new TimeoutActionProcessor(
            $testContextGenerator,
            $this->deliveryExecutionPropertyService,
            static::getContainer()->get(ItemSessionService::class),
            $this->createMock(TimerService::class),
            $eventDispatcherMock,
            static::getContainer()->get('monolog.logger.audit_delivery_execution'),
        );
    }

    public function testGetName(): void
    {
        $this->assertEquals(TimeoutActionProcessor::ACTION_NAME, $this->subject->getActionName());
    }

    public function testItImplementsActionProcessorInterface(): void
    {
        $this->assertInstanceOf(ActionProcessorInterface::class, $this->subject);
    }

    public function testDetectMultiSession()
    {
        $this->createDeliveryExecution();
        $this->expectExceptionMessage('Multiple active sessions detected');
        $this->subject->process($this->deliveryExecution, [
            'id' => 'timeout',
            'name' => 'timeout',
            'parameters' => $this->getParameters([
                'itemIdentifier' => '02',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'itemDuration' => '500',
            ]),
        ]);
    }

    public function testItemStatePreservedIfLateSubmissionAllowed()
    {
        $state = 'some state';
        $this->createDeliveryExecution();
        $this->deliveryExecution->addTemporaryItemState('Q01', 'temporary state');
        $this->subject->process($this->deliveryExecution, [
            'id' => 'timeout',
            'name' => 'timeout',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'Q01',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'itemDuration' => '500',
                'itemState' => $state,
            ]),
        ]);
        $this->assertSame(
            $state,
            $this->deliveryExecution->getExtraStateData()->getItemState('Q01'),
        );
        $this->assertEmpty(
            $this->deliveryExecution->getExtraStateData()->getTemporaryItemStates(),
        );
    }

    public function testItemStatePreservedOnTestPartSubmissionIfLateSubmissionAllowed()
    {
        $state = 'some state';
        $this->createDeliveryExecution();
        $this->deliveryExecution->addTemporaryItemState('Q01', 'temporary state');
        $this->subject->process($this->deliveryExecution, [
            'id' => 'timeout',
            'name' => 'timeout',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'Q01',
                'scope' => TestSessionNavigator::SCOPE_TEST_PART,
                'itemDuration' => '500',
                'itemState' => $state,
            ]),
        ]);
        $this->assertSame(
            $state,
            $this->deliveryExecution->getExtraStateData()->getItemState('Q01'),
        );
        $this->assertEmpty(
            $this->deliveryExecution->getExtraStateData()->getTemporaryItemStates(),
        );
    }

    public function testPublicItemStatePreservedIfLateSubmissionAllowed()
    {
        $state = 'some state';
        $this->createDeliveryExecution();
        $this->deliveryExecution->addTemporaryItemState('Q01', 'temporary state');
        $this->subject->process($this->deliveryExecution, [
            'id' => 'timeout',
            'name' => 'timeout',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'Q01',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'itemDuration' => '500',
                'itemState' => $state,
            ]),
        ]);
        $this->assertSame(
            $state,
            $this->deliveryExecution->getExtraStateData()->getTemporaryItemState('Q01'),
        );
    }

    public function testItemStateRejectedIfLateSubmissionDisallowed()
    {
        $this->createDeliveryExecution(false);
        $this->deliveryExecution->addTemporaryItemState('Q01', 'temporary state');
        $state = 'some state';
        $this->subject->process($this->deliveryExecution, [
            'id' => 'timeout',
            'name' => 'timeout',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'Q01',
                'scope' => TestSessionNavigator::SCOPE_SECTION,
                'itemDuration' => '500',
                'itemState' => $state,
            ]),
        ]);
        $this->assertNull(
            $this->deliveryExecution->getExtraStateData()->getItemState('Q01'),
        );
        $this->assertSame(
            $state,
            $this->deliveryExecution->getExtraStateData()->getTemporaryItemState('Q01'),
        );
    }

    public function testFinishItemIfAllowLateSubmissionOffAndGuidedNavigationOn()
    {
        $this->createDeliveryExecution(false);
        $this->deliveryExecution->addTemporaryItemState('Q01', 'temporary state');
        $state = 'some state';
        $this->subject->process($this->deliveryExecution, [
            'id' => 'timeout',
            'name' => 'timeout',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'Q01',
                'scope' => TestSessionNavigator::SCOPE_SECTION,
                'itemDuration' => '500',
                'itemState' => $state,
            ]),
        ]);
        $this->assertNull(
            $this->deliveryExecution->getExtraStateData()->getItemState('Q01'),
        );
        $this->assertSame(
            $state,
            $this->deliveryExecution->getExtraStateData()->getTemporaryItemState('Q01'),
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

    private function getParameters(array $overridden = []): array
    {
        return array_merge(
            [
                'itemResponse' => '{"RESPONSE":{"base":null}}',
                'itemState' => '{"RESPONSE":{"response":{"base":null}}}',
            ],
            $overridden,
        );
    }

    private function createDeliveryExecution($allowLateSubmission = true): void
    {
        $deliveryId = $allowLateSubmission ? 'BasicTestWithTimeAndLateSubmission' : 'BasicTestFullTimerStack';
        $this->copyCompiledTestToStorage(packageName: $deliveryId);

        $this->deliveryExecution = $this->createTestDeliveryExecution(
            "userId#$deliveryId#resultId#tenantId",
            $deliveryId,
            'tenantId',
            ['ltiLaunchParameters'],
            null,
        );

        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($this->deliveryExecution);
        $testSession->beginTestSession();
        $testSession->beginAttempt();
        $this->deliveryExecutionPropertyService->persistTestSession($testSession);
    }
}
