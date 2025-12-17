<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Factory;

use OAT\Bundle\DocumentManagerBundle\Exception\DocumentDriverException;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;
use Throwable;
use League\Flysystem\FilesystemException;

class ExceptionConverterFactory
{
    public const EXCEPTION_CODES = [
        FilesystemException::class => 101,
        DocumentNormalizerException::class => 102,
        DocumentDriverException::class => 103,
    ];

    private const DEFAULT_ERROR_CODE = 500;

    public function convert(Throwable $exception): array
    {
        return [
            'code' => $this->getErrorCode($exception),
            'exceptionCode' => $exception->getCode(),
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'trace' => $exception->getTrace()[0] ?? [],
        ];
    }

    private function getErrorCode(Throwable $exception): int
    {
        $fullClass = get_class($exception);
        $classParts = explode('\\', $fullClass);
        $className = end($classParts);
        $handlerMethod = "handle$className";

        if (method_exists($this, $handlerMethod)) {
            return $this->{$handlerMethod}($exception);
        }

        return self::EXCEPTION_CODES[$fullClass] ?? self::DEFAULT_ERROR_CODE;
    }
}
