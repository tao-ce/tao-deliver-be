<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\ActionProcessor\ActionProcessorInterface;
use App\TestRunner\ActionProcessor\FlagItemActionProcessor;
use App\TestRunner\Event\TestSessionInteractionEvent;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class FlagItemActionProcessorTest extends KernelTestCase
{
    use DomainTestingTrait;
    use QtiTestingTrait;
    use LoggerTestingTrait;

    /** @var EventDispatcherInterface|MockObject */
    private $eventDispatcherMock;

    /** @var DeliveryExecution */
    private $deliveryExecution;

    /** @var FlagItemActionProcessor */
    private $subject;

    /** @var LoggerInterface */
    private $logger;

    public function setUp(): void
    {
        self::bootKernel();
        $this->setUpTestLogHandler();
        $this->copyCompiledTestToStorage();

        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->deliveryExecution = $this->createTestDeliveryExecution(
            'userId#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            ['ltiLaunchParams'],
            null,
        );

        /** @var DeliveryExecutionPropertyService $deliveryExecutionPropertyService */
        $deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);

        $testSession = $deliveryExecutionPropertyService->fetchTestSession($this->deliveryExecution);
        $testSession->beginTestSession();
        $testSession->beginAttempt();
        $deliveryExecutionPropertyService->persistTestSession($testSession);

        $this->logger = static::getContainer()->get('monolog.logger.audit_delivery_execution');

        $this->subject = new FlagItemActionProcessor(
            $this->eventDispatcherMock,
            $deliveryExecutionPropertyService,
            $this->logger,
        );
    }

    public function testGetName(): void
    {
        $this->assertEquals(
            FlagItemActionProcessor::ACTION_NAME,
            $this->subject->getActionName(),
        );
    }

    public function testProcessFlagItem(): void
    {
        $this->eventDispatcherMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(TestSessionInteractionEvent::class),
            );

        $this->assertEquals(
            [
                'success' => true,
                'name' => 'flagItem',
                'id' => 'flagItem_1234',
                'errorCode' => null,
                'errorMessage' => null,
                'values' => [],
            ],
            $this->subject->process(
                $this->deliveryExecution,
                [
                    'name' => 'flagItem',
                    'id' => 'flagItem_1234',
                    'timestamp' => '1234',
                    'parameters' => [
                        'position' => '1',
                        'flag' => true,
                    ],
                ],
            ),
        );

        $this->assertTrue(in_array('Item-Q02', $this->deliveryExecution->getExtraStateData()->getFlaggedItems(), true));

        $this->subject->process(
            $this->deliveryExecution,
            [
                'name' => 'flagItem',
                'id' => 'flagItem_12345',
                'timestamp' => '12345',
                'parameters' => [
                    'position' => '2',
                    'flag' => true,
                ],
            ],
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#Basic#resultId#tenantId] - test taker changed the state of Item: [Item-Q02] to flagged',
            Logger::INFO,
            'audit_delivery_execution',
        );

        $this->assertEquals(['Item-Q02', 'Item-Q03'], $this->deliveryExecution->getExtraStateData()->getFlaggedItems());
    }

    public function testIsImplementingActionProcessorInterface(): void
    {
        $this->assertInstanceOf(ActionProcessorInterface::class, $this->subject);
    }

    public function testProcessUnFlagItem(): void
    {
        $this->subject->process(
            $this->deliveryExecution,
            [
                'name' => 'flagItem',
                'id' => 'flagItem_1234',
                'timestamp' => '1234',
                'parameters' => [
                    'position' => '1',
                    'flag' => true,
                ],
            ],
        );

        $this->assertTrue(in_array('Item-Q02', $this->deliveryExecution->getExtraStateData()->getFlaggedItems(), true));


        $this->assertEquals(
            [
                'success' => true,
                'name' => 'flagItem',
                'id' => 'flagItem_1234',
                'errorCode' => null,
                'errorMessage' => null,
                'values' => [],
            ],
            $this->subject->process(
                $this->deliveryExecution,
                [
                    'name' => 'flagItem',
                    'id' => 'flagItem_1234',
                    'timestamp' => '1234',
                    'parameters' => [
                        'position' => '1',
                        'flag' => false,
                    ],
                ],
            ),
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#Basic#resultId#tenantId] - test taker changed the state of Item: [Item-Q02] to un-flagged',
            Logger::INFO,
            'audit_delivery_execution',
        );

        $this->assertFalse(in_array('Item-Q02', $this->deliveryExecution->getExtraStateData()->getFlaggedItems(), true));
    }
}
