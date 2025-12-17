<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\ActionProcessor\Handler\CantPerformActionException;
use App\TestRunner\ActionProcessor\InitActionProcessor;
use App\TestRunner\Generator\TestContextGenerator;
use App\TestRunner\Generator\TestMapGenerator;
use App\TestRunner\Service\GetItemService;
use App\TestRunner\Service\TestSessionNavigator;
use App\TestRunner\Service\TimerService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class InitActionProcessorTest extends TestCase
{
    private const EXPECTED_ACTION_NAME = 'init';

    private InitActionProcessor $subject;

    protected function setUp(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $deliveryExecutionPropertyServiceMock = $this->createMock(DeliveryExecutionPropertyService::class);
        $testContextGeneratorMock = $this->createMock(TestContextGenerator::class);
        $testMapGeneratorMock = $this->createMock(TestMapGenerator::class);
        $timerServiceMock = $this->createMock(TimerService::class);
        $auditDeliveryExecutionLoggerMock = $this->createMock(LoggerInterface::class);
        $ltiCustomSettingsMock = $this->createMock(LtiCustomSettings::class);

        $this->subject = new InitActionProcessor(
            $eventDispatcherMock,
            $deliveryExecutionPropertyServiceMock,
            $testContextGeneratorMock,
            $testMapGeneratorMock,
            $timerServiceMock,
            $auditDeliveryExecutionLoggerMock,
            $ltiCustomSettingsMock,
            $this->createMock(TestSessionNavigator::class),
            $this->createMock(GetItemService::class),
        );
    }

    public function testSuccessAccessibilityValidation(): void
    {
        $this->subject->validateAvailability(DeliveryExecution::STATUS_INTERACTING);
        $this->subject->validateAvailability(DeliveryExecution::STATUS_SUSPENDED);
        self::assertTrue(true);
    }

    public function testFailedAccessibilityValidationForUnavailableStatus(): void
    {
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
