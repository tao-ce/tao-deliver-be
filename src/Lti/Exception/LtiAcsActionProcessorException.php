<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class LtiAcsActionProcessorException extends HttpException
{
    public function __construct(?string $message = '', ?Throwable $previous = null, array $headers = [], ?int $code = 0)
    {
        parent::__construct(500, $message, $previous, $headers, $code);
    }
}
