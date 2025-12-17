<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Publication;

use App\Responder\SerializerResponder;
use App\Service\Locale\LocaleRetriever;
use App\Service\Publication\CreatePublicationService;
use App\Validator\Publication\CreatePublicationRequestValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreatePublicationAction
{
    public function __construct(
        private SerializerResponder $responder,
        private CreatePublicationRequestValidator $validator,
        private CreatePublicationService $createPublicationService,
        private LocaleRetriever $localeService,
    ) {
    }

    public function __invoke(Request $request, string $tenantId): JsonResponse
    {
        $requestData = $this->validator->getValidatedRequestParameters($request);

        $requestData['configuration']['status'] = $requestData['configuration']['status'] ?? true;

        $publication = $this->createPublicationService->create(
            $requestData['package'],
            $requestData['packageRef'],
            $tenantId,
            $requestData['configuration'],
            $requestData['deliveryId'] ?? null,
            $requestData['locale'] ?? $this->localeService->getDefaultLocale(),
            $requestData['translations'],
        );

        return $this->responder->createJsonResponse(
            ['data' => $publication],
            Response::HTTP_ACCEPTED,
        );
    }
}
