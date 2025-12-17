<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorRole;
use App\Responder\SerializerResponder;
use App\Security\Contract\DeliveryExecutionSessionController;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionLogServiceInterface;
use App\Validator\DeliveryExecution\DeliveryExecutionLogActionValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LogAction implements DeliveryExecutionSessionController
{
    public function __construct(
        private readonly SerializerResponder $responder,
        private readonly DeliveryExecutionLogActionValidator $deliveryExecutionLogActionValidator,
        private readonly DeliveryExecutionLogServiceInterface $deliveryExecutionLogService,
    ) {
    }

    public function __invoke(Request $request, DeliveryExecution $deliveryExecution): JsonResponse
    {
        $this->validateDeliveryExecutionAccessibility($deliveryExecution);

        $data = $this->deliveryExecutionLogActionValidator->getValidatedRequestParameters($request);
        $this->deliveryExecutionLogService->log(
            $deliveryExecution,
            DeliveryExecutionActorRole::from($data['issuer']),
            $data['reason'],
        );

        return $this->responder->createJsonResponse(
            ['success' => true],
            Response::HTTP_ACCEPTED,
        );
    }

    /**
     * @param DeliveryExecution $deliveryExecution
     */
    private function validateDeliveryExecutionAccessibility(DeliveryExecution $deliveryExecution): void
    {
        if ($deliveryExecution->isStateFinal()) {
            throw new HttpDeliveryExecutionClosedException(
                sprintf(
                    'Access to this delivery execution [%s] is not accessible because it is closed',
                    $deliveryExecution->getId(),
                ),
                Response::HTTP_ACCEPTED,
            );
        }
    }
}
