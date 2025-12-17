<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Registry;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Monolog\Logger;

/** @package App\Registry */
class LoggerRegistry
{
    public const DEFAULT_CHANNEL = 'default';

    /** @var LoggerInterface */
    private $defaultLogger;

    /** @var Logger[] */
    private $loggers;

    public function __construct(LoggerInterface $logger, iterable $taggedLoggers = [])
    {
        $this->defaultLogger = $logger;

        foreach ($taggedLoggers as $taggedLogger) {
            $this->addLogger($taggedLogger);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getLoggerForChannel(mixed $channel = null): LoggerInterface
    {
        if ($channel === self::DEFAULT_CHANNEL || $channel === null) {
            return $this->defaultLogger;
        }

        foreach ($this->loggers as $logger) {
            if ($logger->getName() === $channel) {
                return $logger;
            }
        }

        throw new InvalidArgumentException(sprintf('Cannot find logger for "%s" channel', $channel));
    }

    public function getAvailableChannels(): array
    {
        $channels = [
            self::DEFAULT_CHANNEL,
        ];

        foreach ($this->loggers as $logger) {
            $channels[] = $logger->getName();
        }

        return $channels;
    }

    private function addLogger(Logger $logger): void
    {
        $this->loggers[] = $logger;
    }
}
