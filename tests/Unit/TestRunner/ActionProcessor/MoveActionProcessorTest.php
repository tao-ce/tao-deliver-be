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
use App\TestRunner\ActionProcessor\MoveActionProcessor;
use App\TestRunner\Event\DeliveryExecutionClosedEvent;
use App\TestRunner\Event\TestSessionInteractionEvent;
use App\TestRunner\Generator\TestContextGenerator;
use App\TestRunner\Service\BatteryNavigationService;
use App\TestRunner\Service\GetItemService;
use App\TestRunner\Service\ItemSessionService;
use App\TestRunner\Service\TestSessionNavigator;
use App\TestRunner\Service\TimerService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use InvalidArgumentException;
use Monolog\Logger;
use OAT\Bundle\QtiBundle\Factory\VariableStateFactory;
use PHPUnit\Framework\MockObject\MockObject;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\AssessmentTestSessionState;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MoveActionProcessorTest extends KernelTestCase
{
    use DomainTestingTrait;
    use QtiTestingTrait;
    use LoggerTestingTrait;
    use MessengerTestingTrait;

    private const EXPECTED_ACTION_NAME = 'move';

    private const DELIVERY_EXECUTION_ID = 'userId#Basic#resultId#tenantId';

    private MoveActionProcessor $subject;
    private DeliveryExecution $deliveryExecution;
    private EventDispatcherInterface|MockObject $eventDispatcherMock;
    private AssessmentTestSession $testSession;
    private TimerService|MockObject $timerServiceMock;
    private BatteryNavigationService|MockObject $batteryNavigationService;

    public function setUp(): void
    {
        static::bootKernel();

        $this->setUpTestLogHandler();

        $variableStateFactory = static::getContainer()->get(VariableStateFactory::class);
        $testContextGenerator = static::getContainer()->get(TestContextGenerator::class);
        $testSessionNavigator = static::getContainer()->get(TestSessionNavigator::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->timerServiceMock = $this->createMock(TimerService::class);
        $this->batteryNavigationService = $this->createMock(BatteryNavigationService::class);

        // @fixme We should probably use mocks for all services instead of getting them from the container

        /** @var DeliveryExecutionPropertyService $deliveryExecutionPropertyService */
        $deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);
        $this->subject = new MoveActionProcessor(
            $this->eventDispatcherMock,
            $deliveryExecutionPropertyService,
            $testContextGenerator,
            new ItemSessionService(
                $variableStateFactory,
                $deliveryExecutionPropertyService,
                $this->timerServiceMock,
            ),
            $testSessionNavigator,
            static::getContainer()->get('monolog.logger.audit_delivery_execution'),
            $this->batteryNavigationService,
            $this->createMock(GetItemService::class),
        );

        $this->copyCompiledTestToStorage([
            'compact-test.xml',
            'Item-Q01/item.json',
            'Item-Q02/item.json',
            'Item-Q03/item.json',
        ]);

        $this->deliveryExecution = $this->createTestDeliveryExecution(
            self::DELIVERY_EXECUTION_ID,
            'Basic',
            'tenantId',
            ['ltiLaunchParameters'],
            null,
        );

        $this->testSession = $deliveryExecutionPropertyService->fetchTestSession($this->deliveryExecution);

        $this->testSession->beginTestSession();
        $this->testSession->beginAttempt();

        $deliveryExecutionPropertyService->persistTestSession($this->testSession);
    }

    public function testGetName(): void
    {
        $this->assertEquals(MoveActionProcessor::ACTION_NAME, $this->subject->getActionName());
    }

    public function testItImplementsActionProcessorInterface(): void
    {
        $this->assertInstanceOf(ActionProcessorInterface::class, $this->subject);
    }

    public function testItCanMoveToNextItem(): void
    {
        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(TestSessionInteractionEvent::class),
            );

        $this->timerServiceMock
            ->expects($this->once())
            ->method('endServerTimer')
            ->with(
                $this->isInstanceOf(DeliveryExecution::class),
                $this->isInstanceOf(AssessmentTestSession::class),
                12.34,
            );

        $this->timerServiceMock
            ->expects($this->once())
            ->method('startServerTimer')
            ->with(
                $this->isInstanceOf(DeliveryExecution::class),
                $this->isInstanceOf(AssessmentTestSession::class),
            );

        $result = $this->subject->process($this->deliveryExecution, [
            'id' => 'move_1',
            'name' => 'move',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'Item-Q01',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'direction' => TestSessionNavigator::DIRECTION_NEXT,
                'itemDuration' => '12.34',
            ]),
        ]);

        $this->assertEquals('Item-Q02', $result['values']['testContext']['itemIdentifier']);
        $this->assertEquals(AssessmentTestSessionState::INTERACTING, $this->testSession->getState());

        $this->assertHasLogRecordWithMessage(
            sprintf(
                '[%s] - test taker has submitted the following item: [Item-Q01] with ItemResponse: [{}] and itemState: [{}]',
                self::DELIVERY_EXECUTION_ID,
            ),
            Logger::INFO,
            'audit_delivery_execution',
        );

        $this->assertHasLogRecordWithMessage(
            sprintf(
                '[%s] - test taker has navigated from item [%s] to item [%s]; direction [%s]; scope [%s]',
                self::DELIVERY_EXECUTION_ID,
                'Item-Q01',
                'Item-Q02',
                TestSessionNavigator::DIRECTION_NEXT,
                TestSessionNavigator::SCOPE_ITEM,
            ),
            Logger::INFO,
            'audit_delivery_execution',
        );
    }

    public function testItEndsSessionAfterLastItem(): void
    {
        $this->eventDispatcherMock
            ->expects($this->exactly(4))
            ->method('dispatch')
            ->withConsecutive(
                [$this->isInstanceOf(TestSessionInteractionEvent::class)],
                [$this->isInstanceOf(TestSessionInteractionEvent::class)],
                [$this->isInstanceOf(DeliveryExecutionClosedEvent::class)],
                [$this->isInstanceOf(TestSessionInteractionEvent::class)],
            );

        $this->timerServiceMock
            ->expects($this->exactly(3))
            ->method('endServerTimer')
            ->with(
                $this->isInstanceOf(DeliveryExecution::class),
                $this->isInstanceOf(AssessmentTestSession::class),
                12.34,
            );

        $this->timerServiceMock
            ->expects($this->exactly(2))
            ->method('startServerTimer')
            ->with(
                $this->isInstanceOf(DeliveryExecution::class),
                $this->isInstanceOf(AssessmentTestSession::class),
            );

        $result = $this->subject->process($this->deliveryExecution, [
            'id' => 'move_1',
            'name' => 'move',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'Item-Q01',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'direction' => TestSessionNavigator::DIRECTION_NEXT,
                'itemDuration' => '12.34',
            ]),
        ]);

        $this->assertEquals('Item-Q02', $result['values']['testContext']['itemIdentifier']);
        $this->assertEquals(AssessmentTestSessionState::INTERACTING, $result['values']['testContext']['state']);

        $result = $this->subject->process($this->deliveryExecution, [
            'id' => 'move_1',
            'name' => 'move',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'Item-Q02',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'direction' => TestSessionNavigator::DIRECTION_NEXT,
                'itemDuration' => '12.34',
            ]),
        ]);

        $this->assertEquals('Item-Q03', $result['values']['testContext']['itemIdentifier']);
        $this->assertEquals(AssessmentTestSessionState::INTERACTING, $result['values']['testContext']['state']);

        $result = $this->subject->process($this->deliveryExecution, [
            'id' => 'move_1',
            'name' => 'move',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'Item-Q03',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'direction' => TestSessionNavigator::DIRECTION_NEXT,
                'itemDuration' => '12.34',
            ]),
        ]);

        $this->assertEquals(null, $result['values']['testContext']['itemIdentifier']);
        $this->assertEquals(AssessmentTestSessionState::CLOSED, $result['values']['testContext']['state']);

        $this->assertHasLogRecordWithMessage(
            sprintf(
                '[%s] - test taker has submitted the following item: [Item-Q03] with ItemResponse: [{}] and itemState: [{}]',
                self::DELIVERY_EXECUTION_ID,
            ),
            Logger::INFO,
            'audit_delivery_execution',
        );
    }

    public function testItThrowsExceptionIfMovingBackwardIsNotAllowed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('It is not possible to move backward');

        $this->subject->process($this->deliveryExecution, [
            'id' => 'move_1',
            'name' => 'move',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'Item-Q01',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'direction' => TestSessionNavigator::DIRECTION_BACK,
                'itemDuration' => '12.34',
            ]),
        ]);
    }

    public function testItSavesToolState(): void
    {
        $this->subject->process($this->deliveryExecution, [
            'id' => 'move_1',
            'name' => 'move',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'Item-Q01',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'direction' => TestSessionNavigator::DIRECTION_NEXT,
                'toolStates' => 'the state of the tools',
                'itemDuration' => '12.34',
            ]),
        ]);

        $this->assertEquals(['the state of the tools'], $this->deliveryExecution->getExtraStateData()->getToolStates());
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

    public function testMoveFromLasBatteryDeliveryProvidesBatteryContext(): void
    {
        $this->eventDispatcherMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [$this->isInstanceOf(DeliveryExecutionClosedEvent::class)],
                [$this->isInstanceOf(TestSessionInteractionEvent::class)],
            );

        $this->batteryNavigationService
            ->expects($this->once())
            ->method('getBatteryContext')
            ->with(
                $this->deliveryExecution,
                [
                    'id' => 'move_1',
                    'name' => 'move',
                    'parameters' => $this->getParameters([
                        'itemIdentifier' => 'Item-Q01',
                        'scope' => TestSessionNavigator::SCOPE_TEST,
                        'direction' => TestSessionNavigator::DIRECTION_NEXT,
                        'itemDuration' => '12.34',
                    ]),
                ],
            )->willReturn([
                'state' => 'startNextDelivery',
                'currentDeliveryExecution' => 'Basic',
                'nextDeliveryExecution' => 'nextDeliveryId',
                'passwordProtected' => true,
            ]);

        $result = $this->subject->process(
            $this->deliveryExecution,
            [
                'id' => 'move_1',
                'name' => 'move',
                'parameters' => $this->getParameters([
                    'itemIdentifier' => 'Item-Q01',
                    'scope' => TestSessionNavigator::SCOPE_TEST,
                    'direction' => TestSessionNavigator::DIRECTION_NEXT,
                    'itemDuration' => '12.34',
                ]),
            ],
        );

        $this->assertEquals(null, $result['values']['testContext']['itemIdentifier']);
        $this->assertEquals(AssessmentTestSessionState::CLOSED, $result['values']['testContext']['state']);
        $this->assertEquals(
            [
                'state' => 'startNextDelivery',
                'currentDeliveryExecution' => 'Basic',
                'nextDeliveryExecution' => 'nextDeliveryId',
                'passwordProtected' => true,
            ],
            $result['values']['batteryContext'],
        );
    }

    private function getParameters(array $overridden = []): array
    {
        return array_merge(
            [
                'itemResponse' => '{}',
                'itemState' => '{}',
                'direction' => TestSessionNavigator::DIRECTION_NEXT,
            ],
            $overridden,
        );
    }
}
