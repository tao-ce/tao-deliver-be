<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Delivery;

use App\Repository\DeliveryRepository;
use App\Responder\SerializerResponder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ListDeliveriesAction
{
    public function __construct(
        private DeliveryRepository $deliveryRepository,
        private SerializerResponder $responder,
    ) {
    }

    public function __invoke(Request $request, string $tenantId): JsonResponse
    {
        $deliveries = $this->deliveryRepository->findCollectionByTenantId($tenantId);

        return $this->responder->createJsonResponse(['data' => $deliveries]);
    }
}
