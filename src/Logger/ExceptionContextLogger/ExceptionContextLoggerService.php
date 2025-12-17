<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Logger\ExceptionContextLogger;

use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * @author Kiryl Poyu - kyril.poyu@taotesting.com
 */
class ExceptionContextLoggerService
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function logException(Throwable $throwable): void
    {
        if (
            $throwable instanceof DocumentNotFoundException
            || $throwable instanceof HttpExceptionInterface && $throwable->getStatusCode() < 500
        ) {
            return;
        }

        $flatException = FlattenException::createFromThrowable($throwable);
        $this->logger->error($flatException->getMessage(), ['trace' => $flatException->toArray()]);
    }
}
