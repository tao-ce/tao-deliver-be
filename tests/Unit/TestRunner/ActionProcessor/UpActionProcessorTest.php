<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\TestRunner\ActionProcessor\Handler\CantPerformActionException;
use App\TestRunner\ActionProcessor\UpActionProcessor;
use App\Tests\Traits\DomainTestingTrait;
use PHPUnit\Framework\TestCase;

class UpActionProcessorTest extends TestCase
{
    use DomainTestingTrait;

    private const EXPECTED_ACTION_NAME = 'up';

    private UpActionProcessor $upActionProcessor;

    public function setUp(): void
    {
        $this->upActionProcessor = new UpActionProcessor();
    }

    public function testGetName(): void
    {
        $this->assertEquals(UpActionProcessor::ACTION_NAME, $this->upActionProcessor->getActionName());
    }

    public function testProcess(): void
    {
        $this->assertEquals([
            'success' => true,
            'name' => 'testAction',
            'id' => 'testActionId',
            'errorCode' => null,
            'errorMessage' => null,
            'values' => [],

        ], $this->upActionProcessor->process($this->createTestDeliveryExecution(), [
            'name' => 'testAction',
            'id' => 'testActionId',
        ]));
    }

    public function testSuccessAccessibilityValidation(): void
    {
        $this->upActionProcessor->validateAvailability(DeliveryExecution::STATUS_INTERACTING);
        self::assertTrue(true);
    }

    public function testFailedAccessibilityValidationForUnavailableStatus(): void
    {
        try {
            $this->upActionProcessor->validateAvailability(DeliveryExecution::STATUS_SUSPENDED);
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
            $this->upActionProcessor->validateAvailability(DeliveryExecution::STATUS_TERMINATED);
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
            $this->upActionProcessor->validateAvailability(DeliveryExecution::STATUS_CLOSED);
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
            $this->upActionProcessor->validateAvailability(DeliveryExecution::STATUS_INITIAL);
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
