<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Delivery;

use App\Responder\SerializerResponder;
use App\Service\Delivery\AttachLanguageToDeliveryService;
use App\Validator\Delivery\AttachLanguageToDeliveryRequestValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AttachLanguageToDeliveryAction
{
    public function __construct(
        private SerializerResponder $responder,
        private AttachLanguageToDeliveryRequestValidator $validator,
        private AttachLanguageToDeliveryService $attachLanguageToDeliveryService,
    ) {
    }

    public function __invoke(Request $request, string $id, string $locale): JsonResponse
    {
        $requestData = $this->validator->getValidatedRequestParameters($request);

        $delivery = $this->attachLanguageToDeliveryService->attach(
            $id,
            $locale,
            $requestData['package'] ?? null,
            $requestData['packageRef'] ?? null,
        );

        return $this->responder->createJsonResponse(
            ['data' => $delivery],
            Response::HTTP_ACCEPTED,
        );
    }
}
