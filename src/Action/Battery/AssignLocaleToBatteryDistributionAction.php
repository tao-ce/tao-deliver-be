<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Action\Battery;

use App\Responder\SerializerResponder;
use App\Service\Battery\AssignLocaleToBatteryDistributionService;
use App\Validator\DeliveryExecution\SetLocaleValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AssignLocaleToBatteryDistributionAction
{
    public function __construct(
        private SerializerResponder $responder,
        private SetLocaleValidator $setLocaleValidator,
        private AssignLocaleToBatteryDistributionService $assignLocaleToBatteryDistributionService,
    ) {
    }

    public function __invoke(Request $request, string $batteryDistributionId): JsonResponse
    {
        $data = $this->setLocaleValidator->getValidatedRequestParameters($request);

        $this->assignLocaleToBatteryDistributionService->assign($batteryDistributionId, $data['locale']);
        return $this->responder->createJsonResponse([
            'message' => 'Locale assigned successfully.',
        ]);
    }
}
