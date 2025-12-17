<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\TestRunner\ActionProcessor\ActionProcessorInterface;
use App\TestRunner\ActionProcessor\CommentActionProcessor;
use App\TestRunner\ActionProcessor\Handler\CantPerformActionException;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CommentActionProcessorTest extends KernelTestCase
{
    use DomainTestingTrait;
    use LoggerTestingTrait;

    private const EXPECTED_ACTION_NAME = 'comment';

    private CommentActionProcessor $subject;

    public function setUp(): void
    {
        static::bootKernel();
        $this->setUpTestLogHandler();

        $this->subject = static::getContainer()->get(CommentActionProcessor::class);
    }

    public function testGetName(): void
    {
        $this->assertEquals(CommentActionProcessor::ACTION_NAME, $this->subject->getActionName());
    }

    public function testIsImplementingActionProcessorInterface(): void
    {
        $this->assertInstanceOf(ActionProcessorInterface::class, $this->subject);
    }

    public function testProcess(): void
    {
        $deliverExecution = $this->createTestDeliveryExecution();

        $this->assertEquals(
            [
                'success' => true,
                'name' => 'comment',
                'id' => 'comment_123',
                'errorCode' => null,
                'errorMessage' => null,
                'values' => [],
            ],
            $this->subject->process(
                $deliverExecution,
                [
                    'name' => 'comment',
                    'id' => 'comment_123',
                    'timestamp' => microtime(),
                    'parameters' => [
                        'comment' => 'just a comment action',
                        'itemIdentifier' => '123456',
                    ],
                ],
            ),
        );

        $this->subject->process(
            $deliverExecution,
            [
                'name' => 'comment',
                'id' => 'comment_1234',
                'timestamp' => microtime(),
                'parameters' => [
                    'comment' => 'second comment action',
                    'itemIdentifier' => '123456',
                ],
            ],
        );

        $this->assertEquals(
            ['just a comment action', 'second comment action'],
            $deliverExecution->getExtraStateData()->getCommentsForItem('123456'),
        );

        $this->assertHasLogRecordWithMessage(
            '[userId#deliveryId#resultId#tenantId] - test taker has added a comment for the following Item: [123456]',
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
