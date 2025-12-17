<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\ActionProcessor\InitActionProcessor;
use App\TestRunner\ActionProcessor\MoveActionProcessor;
use App\TestRunner\Generator\TestContextGenerator;
use App\TestRunner\Service\BatteryNavigationService;
use App\TestRunner\Service\GetItemService;
use App\TestRunner\Service\ItemSessionService;
use App\TestRunner\Service\TestSessionNavigator;
use App\TestRunner\Service\TimerService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use OAT\Bundle\QtiBundle\Factory\VariableStateFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use qtism\runtime\pci\json\Unmarshaller;
use qtism\runtime\tests\AssessmentTestSession;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Monolog\Logger;

class MoveActionProcessorTest extends KernelTestCase
{
    use DomainTestingTrait;
    use QtiTestingTrait;
    use LoggerTestingTrait;

    private DeliveryExecutionPropertyService $deliveryExecutionPropertyService;
    private EventDispatcherInterface $eventDispatcherMock;
    private DeliveryExecution $deliveryExecution;
    private AssessmentTestSession $testSession;
    private MoveActionProcessor $moveActionProcessor;
    private TimerService $timerServiceMock;
    private InitActionProcessor $initActionProcessor;
    private BatteryNavigationService|MockObject $batteryNavigationService;

    public function setUp(): void
    {
        static::bootKernel();

        $this->setUpTestLogHandler();

        $testContextGenerator = static::getContainer()->get(TestContextGenerator::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->timerServiceMock = $this->createMock(TimerService::class);
        $this->deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);
        $this->batteryNavigationService = $this->createMock(BatteryNavigationService::class);

        $unmarshaller = static::getContainer()->get(Unmarshaller::class);
        $variableStateFactory = new VariableStateFactory($unmarshaller);

        /** @var LoggerInterface $logger */
        $logger = static::getContainer()->get('monolog.logger.audit_delivery_execution');
        $this->moveActionProcessor = new MoveActionProcessor(
            $this->eventDispatcherMock,
            $this->deliveryExecutionPropertyService,
            $testContextGenerator,
            new ItemSessionService(
                $variableStateFactory,
                $this->deliveryExecutionPropertyService,
                $this->timerServiceMock,
            ),
            new TestSessionNavigator($this->deliveryExecutionPropertyService, $this->eventDispatcherMock, $logger),
            $logger,
            $this->batteryNavigationService,
            $this->createMock(GetItemService::class),
        );

        $this->copyCompiledTestToStorage([
            'compact-test.xml',
            'Q01/item.json',
            'Q02/item.json',
            'Q03/item.json',
        ], 'BasicNonLinearOneAttempt');

        $this->deliveryExecution = $this->createTestDeliveryExecution(
            'userId#BasicNonLinearOneAttempt#resultId#tenantId',
            'BasicNonLinearOneAttempt',
            'tenantId',
            ['ltiLaunchParameters'],
            null,
            null,
            DeliveryExecution::STATUS_INTERACTING,
        );

        $this->initActionProcessor = static::getContainer()->get(InitActionProcessor::class);

        $this->testSession = $this->deliveryExecutionPropertyService->fetchTestSession($this->deliveryExecution);
        $this->testSession->beginTestSession();
        $this->testSession->beginAttempt();

        $this->deliveryExecutionPropertyService->persistTestSession($this->testSession);

        $this->initActionProcessor->process($this->deliveryExecution, ['name' => 'init', 'id' => 'init_1234']);
    }

    public function testItWillCheckNumberAttemptsOfAnItem(): void
    {
        $this->moveActionProcessor->process($this->deliveryExecution, [
            'id' => 'move_1',
            'name' => 'move',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'Q01',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'direction' => TestSessionNavigator::DIRECTION_NEXT,
                'itemDuration' => '12.34',
            ]),
        ]);


        $response = $this->moveActionProcessor->process($this->deliveryExecution, [
            'id' => 'move_1',
            'name' => 'move',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'Q02',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'direction' => TestSessionNavigator::DIRECTION_BACK,
                'itemDuration' => '11.34',
            ]),
        ]);

        $this->assertTrue($response['success']);
    }

    public function testItDetectsMultipleSessions(): void
    {
        $this->expectExceptionMessage('Multiple active sessions detected');
        $this->expectExceptionCode(Response::HTTP_CONFLICT);

        $this->moveActionProcessor->process($this->deliveryExecution, [
            'id' => 'move_1',
            'name' => 'move',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'Q02',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'direction' => TestSessionNavigator::DIRECTION_NEXT,
                'itemDuration' => '12.34',
            ]),
        ]);
    }

    public function testItLogsResponseProcessingErrors(): void
    {
        $this->copyCompiledTestToStorage([
            'compact-test.xml',
        ], 'FailingResponseProcessing');

        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId1#FailingResponseProcessing#resultId#tenantId',
            'FailingResponseProcessing',
            'tenantId',
            ['ltiLaunchParameters'],
            null,
            null,
            DeliveryExecution::STATUS_INTERACTING,
        );

        $this->initActionProcessor = static::getContainer()->get(InitActionProcessor::class);

        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $testSession->beginTestSession();
        $testSession->beginAttempt();

        $this->deliveryExecutionPropertyService->persistTestSession($testSession);

        $this->initActionProcessor->process($deliveryExecution, ['name' => 'init', 'id' => 'init_1234']);

        $this->moveActionProcessor->process($deliveryExecution, [
            'id' => 'move_1',
            'name' => 'move',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'item-2',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'direction' => TestSessionNavigator::DIRECTION_JUMP,
                'itemDuration' => '12.34',
                'ref' => 3,
            ]),
        ]);

        $response = $this->moveActionProcessor->process($deliveryExecution, [
            'id' => 'move_1',
            'name' => 'move',
            'parameters' => $this->getParameters([
                'itemIdentifier' => 'item-4',
                'scope' => TestSessionNavigator::SCOPE_ITEM,
                'direction' => TestSessionNavigator::DIRECTION_NEXT,
                'itemDuration' => '12.34',
            ]),
        ]);

        $this->assertHasLogRecordWithMessage(
            '[userId1#FailingResponseProcessing#resultId#tenantId] - An error occurred while processing the response: The FieldValue operator only accepts operands with a cardinality of record.',
            Logger::ERROR,
            'audit_delivery_execution',
        );

        $this->assertTrue($response['success']);
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
