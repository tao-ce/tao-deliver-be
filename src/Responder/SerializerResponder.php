<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Responder;

use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

class SerializerResponder
{
    /** @var SerializerInterface */
    private $serializer;

    /** @var bool */
    private $applicationDebug;

    public function __construct(SerializerInterface $serializer, bool $applicationDebug = false)
    {
        $this->serializer = $serializer;
        $this->applicationDebug = $applicationDebug;
    }

    public function createJsonResponse(
        $data,
        int $statusCode = Response::HTTP_OK,
        array $headers = [],
        array $serializerContext = [],
    ): JsonResponse {
        return JsonResponse::fromJsonString(
            $this->serializer->serialize($data, 'json', $serializerContext),
            $statusCode,
            $headers,
        );
    }

    public function createErrorJsonResponse(
        Throwable $exception,
        int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR,
        array $headers = [],
        array $serializerContext = [],
    ): JsonResponse {
        $exception = $this->convertExceptionToHttp($exception);

        $content = ['message' => $exception->getMessage()];

        if ($this->applicationDebug) {
            $content['trace'] = $exception->getTraceAsString();
            $content['previous'] = [];
            $previousException = $exception->getPrevious();

            if ($previousException !== null) {
                do {
                    $content['previous'][] = [
                        'message' => $previousException->getMessage(),
                        'trace' => $previousException->getTraceAsString(),
                    ];
                } while ($previousException = $previousException->getPrevious());
            }
        }

        return JsonResponse::fromJsonString(
            $this->serializer->serialize(['error' => $content], 'json', $serializerContext),
            $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : $statusCode,
            $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : $headers,
        );
    }

    private function convertExceptionToHttp(Throwable $exception): Throwable
    {
        return match (true) {
            $exception instanceof DocumentNotFoundException => new NotFoundHttpException(
                $exception->getMessage(),
                $exception,
                $exception->getCode(),
            ),
            default => $exception,
        };
    }
}
