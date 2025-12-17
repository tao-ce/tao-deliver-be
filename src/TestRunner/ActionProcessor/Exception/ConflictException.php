<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ConflictException extends HttpException
{
    /**
     * By default provided more formal error code (Conflict 409)
     */
    public function __construct(string $message = "", int $code = Response::HTTP_CONFLICT, ?Throwable $previous = null)
    {
        parent::__construct($code, $message, $previous, code: $code);
    }
}
