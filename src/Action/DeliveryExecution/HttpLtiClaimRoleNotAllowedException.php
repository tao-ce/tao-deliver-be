<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\DeliveryExecution;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class HttpLtiClaimRoleNotAllowedException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(
        string $message = 'Access to execution is not accessible to that role',
        private readonly int $statusCode = Response::HTTP_FORBIDDEN,
        ?Throwable $previous = null,
        private array $headers = [],
        ?int $code = 0,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set response headers.
     *
     * @param array $headers Response headers
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }
}
