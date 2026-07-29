<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Security\Battery;

use App\Security\Contract\DeliveryExecutionSessionController;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\Lti\LtiLaunchService;
use App\TestRunner\Service\BatteryNavigationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

readonly class BatteryContinueAction implements DeliveryExecutionSessionController
{
    public function __construct(
        private BatteryNavigationService $batteryNavigationService,
        private LtiLaunchService $ltiLaunchService,
        private DeliveryExecutionServiceInterface $deliveryExecutionService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        string $id,
    ): Response {
        $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecutionOrFail($id);
        $nextDeliveryExecution = $this->batteryNavigationService->getNextDeliveryExecution(
            $deliveryExecution,
            $this->batteryNavigationService->getBatteryDistribution($deliveryExecution),
        );
        $params = $nextDeliveryExecution->getLtiLaunchParameters();

        $this->logger->info(
            sprintf(
                'Continuing battery execution [battery=%s,delivery=%s,deliveryExecution=%s]',
                $params['battery_id'] ?? null,
                $nextDeliveryExecution->getDeliveryId(),
                $nextDeliveryExecution->getId(),
            ),
        );

        return $this->ltiLaunchService->launchTest($nextDeliveryExecution, $params);
    }
}
