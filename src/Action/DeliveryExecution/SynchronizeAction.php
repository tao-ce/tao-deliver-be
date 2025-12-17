<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\DeliveryExecution;

use App\Responder\SerializerResponder;
use App\Service\DeliveryExecution\SynchronizeDeliveryExecutionService;
use App\Service\DeliveryExecution\Dto\DeliveryExecutionDto;
use App\Validator\DeliveryExecution\SynchronizeDeliveryExecutionRequestValidator;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SynchronizeAction
{
    public function __construct(
        private readonly SynchronizeDeliveryExecutionRequestValidator $requestValidator,
        private readonly SynchronizeDeliveryExecutionService $createDeliveryExecutionService,
        private readonly SerializerResponder $responder,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function __invoke(Request $request, string $tenantId): Response
    {
        try {
            $this->createDeliveryExecutionService->synchronize(
                DeliveryExecutionDto::createFromArray(
                    $this->requestValidator->getValidatedRequestParameters($request),
                ),
                $tenantId,
            );
            return new Response(null, Response::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            return $this->responder->createErrorJsonResponse($e, Response::HTTP_BAD_REQUEST);
        }
    }
}
