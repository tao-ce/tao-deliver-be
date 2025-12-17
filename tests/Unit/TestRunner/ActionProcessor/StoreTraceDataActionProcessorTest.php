<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\TestRunner\ActionProcessor\ActionProcessorInterface;
use App\TestRunner\ActionProcessor\Handler\CantPerformActionException;
use App\TestRunner\ActionProcessor\StoreTraceDataActionProcessor;
use App\Tests\Traits\DomainTestingTrait;
use PHPUnit\Framework\TestCase;

class StoreTraceDataActionProcessorTest extends TestCase
{
    use DomainTestingTrait;

    private const EXPECTED_ACTION_NAME = 'storeTraceData';

    private StoreTraceDataActionProcessor $subject;

    public function setUp(): void
    {
        $this->subject = new StoreTraceDataActionProcessor();
    }

    public function testGetName(): void
    {
        $this->assertEquals(
            StoreTraceDataActionProcessor::ACTION_NAME,
            $this->subject->getActionName(),
        );
    }

    public function testIsImplementingActionProcessorInterface(): void
    {
        $this->assertInstanceOf(ActionProcessorInterface::class, $this->subject);
    }

    public function testProcess(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution();
        $this->assertEquals(
            [
                'success' => true,
                'name' => 'storeTraceData',
                'id' => 'storeTraceData_1234',
                'errorCode' => null,
                'errorMessage' => null,
                'values' => [],
            ],
            $this->subject->process(
                $deliveryExecution,
                [
                    'name' => 'storeTraceData',
                    'id' => 'storeTraceData_1234',
                    'timestamp' => '1234',
                    'parameters' => [
                        'traceData' => ['someData'],
                    ],
                ],
            ),
        );

        $this->assertEquals([['someData']], $deliveryExecution->getExtraStateData()->getTraceData());

        $this->subject->process(
            $deliveryExecution,
            [
                'name' => 'storeTraceData',
                'id' => 'storeTraceData_1234',
                'timestamp' => '1234',
                'parameters' => [
                    'traceData' => ['someOtherData'],
                ],
            ],
        );

        $this->assertEquals([['someData'], ['someOtherData']], $deliveryExecution->getExtraStateData()->getTraceData());
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
