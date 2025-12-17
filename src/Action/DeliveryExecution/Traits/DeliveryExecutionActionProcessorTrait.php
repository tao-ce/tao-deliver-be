<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\DeliveryExecution\Traits;

use App\Responder\SerializerResponder;
use App\TestRunner\ActionProcessor\Exception\ConflictException;
use App\TestRunner\ActionProcessor\Handler\CantPerformActionException;
use App\Validator\Exception\RequestValidationException;
use qtism\common\QtiSdkPackageContentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait DeliveryExecutionActionProcessorTrait
{
    private readonly SerializerResponder $responder;

    protected function createFailResponse(RequestValidationException $exception, ?array $responses): JsonResponse
    {
        return $this->responder->createJsonResponse(
            [
                'success' => false,
                'errorCode' => $exception->getCode(),
                'errorMessage' => $exception->getMessage(),
                // the `responses` must be an array which contains an array of action responses as later we will support
                // multiple channels
                'responses' => $responses ?? [],
            ],
            $this->getErrorCode($exception::class),
        );
    }

    protected function createSuccessResponse(array $responses): JsonResponse
    {
        return $this->responder->createJsonResponse(
            [
                'success' => true,
                'errorCode' => null,
                'errorMessage' => null,
                // the `responses` must be an array which contains an array of action responses as later we will support
                // multiple channels
                'responses' => [$this->filterInternalKeys($responses)],
            ],
            $this->getStatusCode($responses),
        );
    }

    private function getStatusCode(array $actionResponses): int
    {
        $successful = 0;
        $failed = 0;
        $errorCode = Response::HTTP_INTERNAL_SERVER_ERROR;

        foreach ($actionResponses as $actionResponse) {
            if ($actionResponse['success']) {
                $successful++;
            } else {
                $failed++;
                $errorCode = $this->getErrorCode($actionResponse['_exception'] ?? null);
            }
        }

        if ($failed > 0) {
            return $successful > 0 ? Response::HTTP_MULTI_STATUS : $errorCode;
        }

        return Response::HTTP_OK;
    }

    private function getErrorCode(?string $exceptionClass): int
    {
        if ($exceptionClass === null) {
            return Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        if (
            is_a($exceptionClass, QtiSdkPackageContentException::class, true)
            || is_a($exceptionClass, RequestValidationException::class, true)
        ) {
            return Response::HTTP_BAD_REQUEST;
        }

        // The client-side app needs the individual response error code to handle the case
        // when a navigation attempt is made on a terminated action
        if (is_a($exceptionClass, CantPerformActionException::class, true)) {
            return Response::HTTP_OK;
        }

        if (is_a($exceptionClass, ConflictException::class, true)) {
            return Response::HTTP_CONFLICT;
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    private function filterInternalKeys(array $actionResponses): array
    {
        return array_map(function ($actionResponse) {
            return array_filter($actionResponse, fn($key) => !str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY);
        }, $actionResponses);
    }
}
