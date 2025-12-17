<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidatorException;

class RequestValidationException extends ValidatorException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getHeaders(): array
    {
        return [];
    }
}
