<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Repository\DeliveryRepository;
use App\Service\Battery\BatteryService;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\ActionProcessor\ActionProcessorInterface;
use App\TestRunner\ActionProcessor\MoveActionProcessor;
use App\TestRunner\ActionProcessor\SkipActionProcessor;
use App\TestRunner\Event\Control\DeliveryExecutionControlEvent;
use App\TestRunner\Event\DeliveryExecutionClosedEvent;
use App\TestRunner\Event\TestSessionInteractionEvent;
use App\TestRunner\Generator\TestContextGenerator;
use App\TestRunner\Service\BatteryNavigationService;
use App\TestRunner\Service\GetItemService;
use App\TestRunner\Service\ItemSessionService;
use App\TestRunner\Service\TestSessionNavigator;
use App\TestRunner\Service\TimerService;
use App\Tests\Traits\CacheTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Carbon\Carbon;
use Monolog\Logger;
use OAT\Bundle\QtiBundle\Factory\VariableStateFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use qtism\runtime\pci\json\Unmarshaller;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\AssessmentTestSessionState;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SkipActionProcessorTest extends KernelTestCase
{
    use DomainTestingTrait;
    use QtiTestingTrait;
    use LoggerTestingTrait;
    use CacheTestingTrait;

    private DeliveryExecutionPropertyService $deliveryExecutionPropertyService;
    private EventDispatcherInterface|MockObject $eventDispatcherMock;
    private DeliveryExecution $deliveryExecution;
    private AssessmentTestSession $testSession;
    private TimerService|MockObject $timerServiceMock;
    private ItemSessionService $itemSessionService;
    private TestSessionNavigator|MockObject $testSessionNavigator;
    private LoggerInterface $logger;
    private SkipActionProcessor $subject;
    private BatteryNavigationService|MockObject $batteryNavigationService;
    private BatteryService $batteryService;
    private DeliveryExecutionServiceInterface $deliveryExecutionService;
    private DeliveryRepository $deliveryRepository;

    public function setUp(): void
    {
        static::bootKernel();

        $this->setUpTestLogHandler();
        $this->setUpTestCache();

        $this->deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);
        $this->logger = static::getContainer()->get('monolog.logger.audit_delivery_execution');
        $this->batteryService = static::getContainer()->get(BatteryService::class);
        $this->deliveryExecutionService = static::getContainer()->get(DeliveryExecutionServiceInterface::class);
        $this->deliveryRepository = static::getContainer()->get(DeliveryRepository::class);

        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->timerServiceMock = $this->createMock(TimerService::class);
        $variableStateFactory = new VariableStateFactory(static::getContainer()->get(Unmarshaller::class));
        $this->itemSessionService = new ItemSessionService(
            $variableStateFactory,
            $this->deliveryExecutionPropertyService,
            $this->timerServiceMock,
        );
        $this->testSessionNavigator = new TestSessionNavigator(
            $this->deliveryExecutionPropertyService,
            $this->eventDispatcherMock,
            $this->logger,
        );
        $this->batteryNavigationService = $this->createMock(BatteryNavigationService::class);

        $this->createSubject();

        $this->expectTestExists();

        $this->deliveryExecution = $this->createDeliveryExecutionWithTestPackage();

        $this->initiateTestSession($this->deliveryExecution);
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testGetName(): void
    {
        $this->assertEquals(SkipActionProcessor::ACTION_NAME, $this->subject->getActionName());
    }

    public function testItImplementsActionProcessorInterface(): void
    {
        $this->assertInstanceOf(ActionProcessorInterface::class, $this->subject);
    }

    public function testSkipsWithIncompleteItemState(): void
    {
        $date = Carbon::now()->toImmutable();

        $this->eventDispatcherMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [$this->isInstanceOf(DeliveryExecutionControlEvent::class)],
                [$this->isInstanceOf(TestSessionInteractionEvent::class)],
            );

        $this->timerServiceMock
            ->expects($this->once())
            ->method('startServerTimer')
            ->with(
                $this->isInstanceOf(DeliveryExecution::class),
                $this->isInstanceOf(AssessmentTestSession::class),
            );

        Carbon::setTestNow($date);

        $expectedResponse = $this->createExpectedResponse('skip_1234', 'Q02');

        $response = $this->subject->process(
            $this->deliveryExecution,
            [
                'name' => SkipActionProcessor::ACTION_NAME,
                'id' => 'skip_1234',
                'timestamp' => '1234',
                'parameters' => [
                    'itemDuration' => '12.34',
                    'itemIdentifier' => 'Q01',
                    'scope' => '',
                    'start' => true,
                    'toolStates' => 'some tool state',
                    'itemState' => '{"boolean": true}',
                ],
            ],
        );

        $this->assertEquals($expectedResponse, $response);
        $this->assertNull($this->deliveryExecution->getExtraStateData()->getItemState('Q01'));
    }

    public function testSkipsWithUnparsableItemState(): void
    {
        $date = Carbon::now()->toImmutable();

        $this->eventDispatcherMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [$this->isInstanceOf(DeliveryExecutionControlEvent::class)],
                [$this->isInstanceOf(TestSessionInteractionEvent::class)],
            );

        $this->timerServiceMock
            ->expects($this->once())
            ->method('startServerTimer')
            ->with(
                $this->isInstanceOf(DeliveryExecution::class),
                $this->isInstanceOf(AssessmentTestSession::class),
            );

        Carbon::setTestNow($date);

        $expectedResponse = $this->createExpectedResponse('skip_1234', 'Q02');

        $response = $this->subject->process(
            $this->deliveryExecution,
            [
                'name' => SkipActionProcessor::ACTION_NAME,
                'id' => 'skip_1234',
                'timestamp' => '1234',
                'parameters' => [
                    'itemDuration' => '12.34',
                    'itemIdentifier' => 'Q01',
                    'scope' => '',
                    'start' => true,
                    'toolStates' => 'some tool state',
                    'itemState' => 'unparsable state',
                ],
            ],
        );

        $this->assertEquals($expectedResponse, $response);
        $this->assertNull($this->deliveryExecution->getExtraStateData()->getItemState('Q01'));
    }

    public function testSkipsItemForwardsByDefaultWhenDirectionIsNotProvided(): void
    {
        $date = Carbon::now()->toImmutable();

        $this->eventDispatcherMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [$this->isInstanceOf(DeliveryExecutionControlEvent::class)],
                [$this->isInstanceOf(TestSessionInteractionEvent::class)],
            );

        $this->timerServiceMock
            ->expects($this->once())
            ->method('startServerTimer')
            ->with(
                $this->isInstanceOf(DeliveryExecution::class),
                $this->isInstanceOf(AssessmentTestSession::class),
            );

        Carbon::setTestNow($date);

        $expectedResponse = $this->createExpectedResponse('skip_1234', 'Q02');

        $response = $this->subject->process(
            $this->deliveryExecution,
            [
                'name' => SkipActionProcessor::ACTION_NAME,
                'id' => 'skip_1234',
                'timestamp' => '1234',
                'parameters' => [
                    'itemDuration' => '12.34',
                    'itemIdentifier' => 'Q01',
                    'scope' => '',
                    'start' => true,
                    'toolStates' => 'some tool state',
                    'itemState' => '{"valid": {"response": ["anything"]}}',
                ],
            ],
        );

        $this->assertEquals($expectedResponse, $response);
        $this->assertEquals(['some tool state'], $this->deliveryExecution->getExtraStateData()->getToolStates());
        $this->assertNull($this->deliveryExecution->getExtraStateData()->getItemState('Q01'));
        $this->assertEquals('{"valid":{"response":["anything"]}}', $this->deliveryExecution->getExtraStateData()->getTemporaryItemState('Q01'));

        $this->assertHasLogRecordWithMessage(
            '[userId#BasicNonLinearMultipleAttempts#resultId#tenantId] - test taker has skipped the the current item: [Q01]',
            Logger::INFO,
            'audit_delivery_execution',
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#BasicNonLinearMultipleAttempts#resultId#tenantId] - test taker has navigated from item [Q01] to item [Q02]; direction [next]; scope [item]',
            Logger::INFO,
            'audit_delivery_execution',
        );
    }

    public function testSkipsItemBackwards(): void
    {
        // Skipping the first item to be able to skip back
        $this->subject->process(
            $this->deliveryExecution,
            [
                'name' => SkipActionProcessor::ACTION_NAME,
                'id' => 'skip_1',
                'timestamp' => '1234',
                'parameters' => [
                    'itemDuration' => '12.34',
                    'itemIdentifier' => 'Q01',
                    'direction' => TestSessionNavigator::DIRECTION_NEXT,
                    'scope' => TestSessionNavigator::SCOPE_ITEM,
                    'start' => true,
                ],
            ],
        );

        // Skipping backwards
        $response = $this->subject->process(
            $this->deliveryExecution,
            [
                'name' => SkipActionProcessor::ACTION_NAME,
                'id' => 'skip_2',
                'timestamp' => '5678',
                'parameters' => [
                    'itemDuration' => '12.34',
                    'itemIdentifier' => 'Q02',
                    'direction' => TestSessionNavigator::DIRECTION_BACK,
                    'scope' => TestSessionNavigator::SCOPE_ITEM,
                    'start' => true,
                    'toolStates' => 'some tool state',
                ],
            ],
        );

        $this->assertEquals($this->createExpectedResponse('skip_2', 'Q01'), $response);
        $this->assertEquals($this->deliveryExecution->getExtraStateData()->getToolStates(), ['some tool state']);

        $this->assertHasLogRecordWithMessage(
            '[userId#BasicNonLinearMultipleAttempts#resultId#tenantId] - test taker has skipped the the current item: [Q01]',
            Logger::INFO,
            'audit_delivery_execution',
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#BasicNonLinearMultipleAttempts#resultId#tenantId] - test taker has navigated from item [Q02] to item [Q01]; direction [previous]; scope [item]',
            Logger::INFO,
            'audit_delivery_execution',
        );
    }

    public function testIgnoresItemWithoutAttemptsLeft(): void
    {
        $this->subject->process($this->deliveryExecution, [
            'id' => 'skip_1',
            'name' => 'skip',
            'parameters' => [
                'itemDuration' => '12.34',
                'itemIdentifier' => 'Q01',
            ],
        ]);

        // Move back to the skipped item
        $moveResponse = $this->doMoveAction(
            $this->deliveryExecution,
            'Q02',
            TestSessionNavigator::DIRECTION_BACK,
        );

        // Skip an item with numberOfAttempts remaining of 0
        $skipResponse = $this->subject->process($this->deliveryExecution, [
            'id' => 'skip_1',
            'name' => 'skip',
            'parameters' => [
                'itemDuration' => '11.34',
                'itemIdentifier' => 'Q01',
            ],
        ]);

        $this->assertTrue($moveResponse['success']);
        $this->assertTrue($skipResponse['success']);
    }

    public function testDoesNotTakeAnAttemptAwayWhenItemWasPreviouslySkipped(): void
    {
        $firstSkipResponse = $this->subject->process(
            $this->deliveryExecution,
            [
                'id' => 'skip_1',
                'name' => 'skip',
                'parameters' => [
                    'direction' => 'next',
                    'itemDuration' => '12.34',
                    'itemIdentifier' => 'Q01',
                    'scope' => 'item',
                ],
            ],
        );

        $this->assertEquals($this->createExpectedResponse('skip_1', 'Q02'), $firstSkipResponse);

        $secondSkipResponse = $this->subject->process(
            $this->deliveryExecution,
            [
                'id' => 'skip_2',
                'name' => 'skip',
                'parameters' => [
                    'direction' => 'previous',
                    'itemDuration' => '12.34',
                    'itemIdentifier' => 'Q02',
                    'scope' => 'item',
                ],
            ],
        );

        // No attempt should be taken when navigating back to the previous item
        $this->assertEquals(
            $this->createExpectedResponse(SkipActionProcessor::ACTION_NAME . '_2', 'Q01'),
            $secondSkipResponse,
        );
    }

    public function testTakesAnAttemptWhenSkippingBackToRecentlyAttemptedItem(): void
    {
        // Moving to item Q02
        $moveResponse = $this->doMoveAction($this->deliveryExecution, 'Q01');

        // Skipping back to item Q01
        $skipBackResponse = $this->subject->process(
            $this->deliveryExecution,
            [
                'id' => 'skip_1',
                'name' => 'skip',
                'parameters' => [
                    'direction' => TestSessionNavigator::DIRECTION_BACK,
                    'itemDuration' => '12.34',
                    'itemIdentifier' => 'Q02',
                    'scope' => TestSessionNavigator::SCOPE_ITEM,
                ],
            ],
        );

        // Ensure we move back to the first item
        $this->assertEquals('Q02', $moveResponse['values']['testContext']['itemIdentifier']);

        $expectedResponse = $this->createExpectedResponse('skip_1', 'Q01');

        // An attempt should be taken when going back to an attempted item from a skip move
        $expectedResponse['values']['testContext']['attempt'] = 2;
        $expectedResponse['values']['testContext']['remainingAttempts'] = 1;

        $this->assertEquals($expectedResponse, $skipBackResponse);
    }

    public function testNotCountAttemptForCurrentItemOnSkip(): void
    {
        // Moving to item Q02
        $this->doMoveAction($this->deliveryExecution, 'Q01');

        // Skipping back to item Q01
        $this->subject->process(
            $this->deliveryExecution,
            [
                'id' => 'skip_1',
                'name' => 'skip',
                'parameters' => [
                    'direction' => TestSessionNavigator::DIRECTION_BACK,
                    'itemDuration' => '12.34',
                    'itemIdentifier' => 'Q02',
                    'scope' => TestSessionNavigator::SCOPE_ITEM,
                ],
            ],
        );

        $skipForwardResponse = $this->subject->process(
            $this->deliveryExecution,
            [
                'id' => 'skip_2',
                'name' => 'skip',
                'parameters' => [
                    'direction' => TestSessionNavigator::DIRECTION_NEXT,
                    'itemDuration' => '12.34',
                    'itemIdentifier' => 'Q01',
                    'scope' => TestSessionNavigator::SCOPE_ITEM,
                ],
            ],
        );

        $expectedResponse = $this->createExpectedResponse('skip_2', 'Q02');

        //
        // We should not count an attempt for the current item when skipping forward
        $expectedResponse['values']['testContext']['attempt'] = 1;
        $expectedResponse['values']['testContext']['remainingAttempts'] = 2;

        $this->assertEquals($expectedResponse, $skipForwardResponse);
    }

    public function testEndsSessionWhenLastItemIsSkipped(): void
    {
        $this->eventDispatcherMock
            ->expects($this->exactly(6))
            ->method('dispatch')
            ->withConsecutive(
                [$this->isInstanceOf(DeliveryExecutionControlEvent::class)],
                [$this->isInstanceOf(TestSessionInteractionEvent::class)],
                [$this->isInstanceOf(DeliveryExecutionControlEvent::class)],
                [$this->isInstanceOf(TestSessionInteractionEvent::class)],
                [$this->isInstanceOf(DeliveryExecutionClosedEvent::class)],
                [$this->isInstanceOf(TestSessionInteractionEvent::class)],
            );

        // First skip
        $this->subject->process($this->deliveryExecution, [
            'id' => 'skip_1',
            'name' => 'skip',
            'parameters' => [
                'itemDuration' => '12.34',
                'itemIdentifier' => 'Q01',
            ],
        ]);

        // Second skip
        $this->subject->process($this->deliveryExecution, [
            'id' => 'skip_2',
            'name' => 'skip',
            'parameters' => [
                'itemDuration' => '12.34',
                'itemIdentifier' => 'Q02',
            ],
        ]);

        $thirdSkipResponse = $this->subject->process($this->deliveryExecution, [
            'id' => 'skip_3',
            'name' => 'skip',
            'parameters' => [
                'itemDuration' => '12.34',
                'itemIdentifier' => 'Q03',
            ],
        ]);

        $this->assertEquals(null, $thirdSkipResponse['values']['testContext']['itemIdentifier']);
        $this->assertEquals(AssessmentTestSessionState::CLOSED, $thirdSkipResponse['values']['testContext']['state']);
    }

    private function createSubject(): void
    {
        $this->subject = new SkipActionProcessor(
            $this->eventDispatcherMock,
            static::getContainer()->get(TestContextGenerator::class),
            $this->deliveryExecutionPropertyService,
            $this->testSessionNavigator,
            $this->itemSessionService,
            $this->createMock(BatteryNavigationService::class),
            $this->logger,
            $this->createMock(GetItemService::class),
        );
    }

    private function expectTestExists(): void
    {
        $this->copyCompiledTestToStorage(
            [
                'compact-test.xml',
                'Q01/item.json',
                'Q02/item.json',
                'Q03/item.json',
            ],
            'BasicNonLinearMultipleAttempts',
        );
    }

    private function createDeliveryExecutionWithTestPackage(): DeliveryExecution
    {
        return $this->createTestDeliveryExecution(
            'userId#BasicNonLinearMultipleAttempts#resultId#tenantId',
            'BasicNonLinearMultipleAttempts',
            'tenantId',
            ['ltiLaunchParameters'],
            null,
            null,
            DeliveryExecution::STATUS_INTERACTING,
        );
    }

    private function initiateTestSession(DeliveryExecution $deliveryExecution): void
    {
        $this->testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $this->testSession->beginTestSession();
        $this->testSession->beginAttempt();

        $this->deliveryExecutionPropertyService->persistTestSession($this->testSession);
    }

    private function createExpectedResponse(string $id, string $itemIdentifier): array
    {
        // Use the last char from the $itemIdentifier to detect the current position.
        // For example: Q01 => position = 0, Q02 => position = 1
        $itemPosition = (int)$itemIdentifier[strlen($itemIdentifier) - 1];
        $itemPosition -= 1;

        return [
            'success' => true,
            'name' => SkipActionProcessor::ACTION_NAME,
            'id' => $id,
            'errorCode' => null,
            'errorMessage' => null,
            'values' => [
                'testContext' => [
                    'state' => 1,
                    'status' => 'interacting',
                    'isProctored' => false,
                    'remainingAttempts' => 2,
                    'isTimeout' => 0,
                    'itemIdentifier' => $itemIdentifier,
                    'attempt' => 1,
                    'itemSessionState' => 1,
                    'needMapUpdate' => false,
                    'itemPosition' => $itemPosition,
                    'timeConstraints' => [],
                    'testPartId' => 'TP01',
                    'sectionId' => 'S01',
                    'canMoveBackward' => ($itemPosition > 0),
                    'rubrics' => '',
                    'allowSkipping' => true,
                    'validateResponses' => false,
                ],
            ],
        ];
    }

    private function doMoveAction(
        DeliveryExecution $deliveryExecution,
        string $itemIdentifier,
        string $direction = TestSessionNavigator::DIRECTION_NEXT,
    ): array {
        $moveActionProcessor = new MoveActionProcessor(
            $this->eventDispatcherMock,
            $this->deliveryExecutionPropertyService,
            static::getContainer()->get(TestContextGenerator::class),
            $this->itemSessionService,
            new TestSessionNavigator(
                $this->deliveryExecutionPropertyService,
                $this->eventDispatcherMock,
                $this->logger,
            ),
            $this->logger,
            $this->batteryNavigationService,
            $this->createMock(GetItemService::class),
        );

        return $moveActionProcessor->process($deliveryExecution, [
            'id' => 'move_1',
            'name' => 'move',
            'parameters' => [
                'itemIdentifier' => $itemIdentifier,
                'itemDuration' => '12.34',
                'itemResponse' => '{}',
                'itemState' => '{}',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'direction' => $direction,
            ],
        ]);
    }
}
