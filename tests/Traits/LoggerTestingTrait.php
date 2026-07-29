<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Traits;

use App\Tests\Helpers\ContainerAwareTestingHelper;
use InvalidArgumentException;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Monolog\Level;

trait LoggerTestingTrait
{
    private TestHandler $testLogHandler;

    private TestHandler $testAuditPlatformLogHandler;

    private TestHandler $testAuditDeliveryExecutionLogHandler;

    protected function setUp(): void
    {
        $this->setUpTestLogHandler();
    }

    protected function setUpTestLogHandler(): void
    {
        ContainerAwareTestingHelper::checkKernelTestCase(static::class);

        /** @var Logger $logger */
        $logger = static::getContainer()->get(LoggerInterface::class);

        /** @var Logger $auditPlatformLogger */
        $auditPlatformLogger = static::getContainer()->get('monolog.logger.audit_platform');

        /** @var Logger $auditDeliveryExecutionLogger */
        $auditDeliveryExecutionLogger = static::getContainer()->get('monolog.logger.audit_delivery_execution');

        $this->testLogHandler = new TestHandler();
        $this->testAuditPlatformLogHandler = new TestHandler();
        $this->testAuditDeliveryExecutionLogHandler = new TestHandler();

        $logger->pushHandler($this->testLogHandler);
        $auditPlatformLogger->pushHandler($this->testAuditPlatformLogHandler);
        $auditDeliveryExecutionLogger->pushHandler($this->testAuditDeliveryExecutionLogHandler);
    }

    protected function getLogRecords(string $channel = 'default'): array
    {
        return $this->getHandlerForChannel($channel)->getRecords();
    }

    protected function assertHasLogRecord(array $record, Level|int $level, string $channel = 'default'): void
    {
        $level = $level instanceof Level ? $level : Level::from($level);
        $this->assertTrue(
            $this->getHandlerForChannel($channel)->hasRecord($record, $level),
            sprintf(
                'Failed asserting that Logger contains record: [%s] %s',
                $level->getName(),
                json_encode($record),
            ),
        );
    }

    protected function assertHasLogRecordWithMessage(string $message, Level|int $level, string $channel = 'default'): void
    {
        $level = $level instanceof Level ? $level : Level::from($level);
        $this->assertTrue(
            $this->getHandlerForChannel($channel)->hasRecordThatContains($message, $level),
            sprintf(
                'Failed asserting that Logger contains record: [%s] %s',
                $level->getName(),
                $message,
            ),
        );
    }

    protected function assertHasNoLogRecordWithMessage(string $message, Level|int $level): void
    {
        $level = $level instanceof Level ? $level : Level::from($level);
        $this->assertFalse(
            $this->testLogHandler->hasRecordThatContains($message, $level),
            sprintf(
                'Failed asserting that Logger does not contain record: [%s] %s',
                $level->getName(),
                $message,
            ),
        );
    }

    protected function assertHasRecordThatPasses(callable $callable, Level|int $level, string $channel = 'default'): void
    {
        $this->assertTrue(
            $this->getHandlerForChannel($channel)->hasRecordThatPasses(
                $callable,
                $level instanceof Level ? $level : Level::from($level),
            ),
        );
    }

    private function getHandlerForChannel(string $channel = 'default'): TestHandler
    {
        return match ($channel) {
            'default' => $this->testLogHandler,
            'audit_platform' => $this->testAuditPlatformLogHandler,
            'audit_delivery_execution' => $this->testAuditDeliveryExecutionLogHandler,
            default => throw new InvalidArgumentException(sprintf('Invalid channel provided: %s', $channel)),
        };
    }
}
