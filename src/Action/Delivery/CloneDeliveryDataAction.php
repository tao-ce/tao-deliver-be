<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Delivery;

use App\Domain\Delivery\Model\Delivery;
use App\Responder\SerializerResponder;
use App\Service\Delivery\CloneDeliveryService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CloneDeliveryDataAction
{
    public function __construct(
        private readonly CloneDeliveryService $service,
        private readonly SerializerResponder $responder,
    ) {
    }

    public function __invoke(Request $request, Delivery $delivery, string $tenantId): JsonResponse
    {
        if ($delivery->getTenantId() !== $tenantId) {
            // Returning a 404 here in order not to expose Delivery IDs to other tenants
            throw new NotFoundHttpException(
                sprintf(
                    'Document class \'%s\' with id \'%s\' not found',
                    $delivery::class,
                    $delivery->getId(),
                ),
            );
        }

        return $this->responder->createJsonResponse(['data' => $this->service->clone($delivery)]);
    }
}
