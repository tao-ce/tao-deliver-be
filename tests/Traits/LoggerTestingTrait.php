<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
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
    /** @var TestHandler */
    private $testLogHandler;

    /** @var TestHandler */
    private $testAuditPlatformLogHandler;

    /** @var TestHandler */
    private $testAuditDeliveryExecutionLogHandler;

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

    protected function assertHasLogRecord(array $record, int $level, string $channel = 'default'): void
    {
        $this->assertTrue(
            $this->getHandlerForChannel($channel)->hasRecord($record, Level::fromValue($level)),
            sprintf(
                'Failed asserting that Logger contains record: [%s] %s',
                Logger::getLevelName($level),
                json_encode($record),
            ),
        );
    }

    protected function assertHasLogRecordWithMessage(string $message, int $level, string $channel = 'default'): void
    {
        $this->assertTrue(
            $this->getHandlerForChannel($channel)->hasRecordThatContains($message, Level::fromValue($level)),
            sprintf(
                'Failed asserting that Logger contains record: [%s] %s',
                Logger::getLevelName($level),
                $message,
            ),
        );
    }

    protected function assertHasNoLogRecordWithMessage(string $message, int $level): void
    {
        $this->assertFalse(
            $this->testLogHandler->hasRecordThatContains($message, Level::fromValue($level)),
            sprintf(
                'Failed asserting that Logger contains record: [%s] %s',
                Logger::getLevelName($level),
                $message,
            ),
        );
    }

    protected function assertHasRecordThatPasses(callable $callable, int $level, string $channel = 'default'): void
    {
        $this->assertTrue($this->getHandlerForChannel($channel)->hasRecordThatPasses($callable, Level::fromValue($level)));
    }

    private function getHandlerForChannel(string $channel = 'default'): TestHandler
    {
        switch ($channel) {
            case 'default':
                return $this->testLogHandler;

            case 'audit_platform':
                return $this->testAuditPlatformLogHandler;

            case 'audit_delivery_execution':
                return $this->testAuditDeliveryExecutionLogHandler;

            default:
                throw new InvalidArgumentException(sprintf('Invalid channel provided: %s', $channel));
        }
    }
}
