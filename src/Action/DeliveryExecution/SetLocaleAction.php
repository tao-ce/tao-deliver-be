<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Repository\DeliveryRepository;
use App\Responder\SerializerResponder;
use App\Security\Contract\DeliveryExecutionSessionController;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use App\TestRunner\ActionProcessor\Exception\ConflictException;
use App\Validator\DeliveryExecution\SetLocaleValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class SetLocaleAction implements DeliveryExecutionSessionController
{
    public function __construct(
        private readonly SerializerResponder $responder,
        private readonly SetLocaleValidator $setLocaleValidator,
        private readonly DeliveryExecutionService $deliveryExecutionService,
        private readonly DeliveryRepository $deliveryRepository,
    ) {
    }

    public function __invoke(Request $request, DeliveryExecution $deliveryExecution): JsonResponse
    {
        $this->validateDeliveryExecutionAccessibility($deliveryExecution);

        $data = $this->setLocaleValidator->getValidatedRequestParameters($request);
        $this->deliveryExecutionService->setLocaleForDeliveryExecution(
            $this->deliveryRepository->find($deliveryExecution->getDeliveryId()),
            $deliveryExecution,
            $data['locale'],
        );

        return $this->responder->createJsonResponse([
            'message' => 'Locale set successfully.',
        ]);
    }

    private function validateDeliveryExecutionAccessibility(DeliveryExecution $deliveryExecution): void
    {
        if ($deliveryExecution->isStateFinal()) {
            throw new ConflictException(
                sprintf(
                    'Access to this delivery execution [%s] is not accessible because it is closed',
                    $deliveryExecution->getId(),
                ),
            );
        }
    }
}
