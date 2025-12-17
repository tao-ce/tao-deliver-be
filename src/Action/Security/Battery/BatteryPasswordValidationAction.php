<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Security\Battery;

use App\Responder\SerializerResponder;
use App\Security\Contract\DeliveryExecutionSessionController;
use App\Service\Battery\BatteryPasswordValidationService;
use App\Service\Battery\Dto\BatteryPasswordValidationCommand;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BatteryPasswordValidationAction implements DeliveryExecutionSessionController
{
    public function __construct(
        private readonly BatteryPasswordValidationService $batteryPasswordValidationService,
        private readonly SerializerResponder $responder,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        try {
            $content = (array)json_decode($request->getContent(), true);

            $this->batteryPasswordValidationService->validate(
                new BatteryPasswordValidationCommand(
                    $content['password'] ?? '',
                    $content['deliveryId'] ?? '',
                    $id,
                ),
            );

            return $this->responder->createJsonResponse([]);
        } catch (Exception $e) {
            return $this->responder->createErrorJsonResponse($e);
        }
    }
}
