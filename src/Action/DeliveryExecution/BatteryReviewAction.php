<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\DeliveryExecution;

use App\Security\Contract\DeliveryExecutionSessionController;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\Lti\LtiLaunchService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class BatteryReviewAction implements DeliveryExecutionSessionController
{
    public function __construct(
        private LtiLaunchService $ltiLaunchService,
        private LoggerInterface $logger,
        private DeliveryExecutionServiceInterface $deliveryExecutionService,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecutionOrFail($id);
        $params = $deliveryExecution->getLtiLaunchParameters();

        if (!$deliveryExecution->isReview()) {
            throw new BadRequestHttpException(
                sprintf(
                    'Delivery execution [%s] is not a review',
                    $deliveryExecution->getId(),
                ),
            );
        }

        $params['result_id'] = $id;
        $params['battery_id'] = $request->get('batteryId');
        $params['client_id'] = 'battery-review.nextgen-stack';

        $this->logger->info(
            sprintf(
                'Continuing battery review [battery=%s,delivery=%s,deliveryExecution=%s]',
                $params['battery_id'] ?? null,
                $deliveryExecution->getDeliveryId(),
                $deliveryExecution->getId(),
            ),
        );

        return $this->ltiLaunchService->launchTest($deliveryExecution, $params);
    }
}
