<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\TestRunner\Service;

use App\Domain\Battery\Model\BatteryDistribution;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Repository\DeliveryRepository;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class BatteryDistributionService
{
    public function __construct(
        private DeliveryExecutionService $deliveryExecutionService,
        private DeliveryRepository $deliveryRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function deleteDeliveryExecutionsLinkedToBatteryDistribution(
        BatteryDistribution $batteryDistribution,
        DeliveryExecution $deliveryExecution,
    ): void {
        foreach ($batteryDistribution->battery->deliveries as $delivery) {
            try {
                $deliveryExecutionToDelete = $delivery->id === $deliveryExecution->getDeliveryId()
                    ? $deliveryExecution
                    : $this->deliveryExecutionService->getDeliveryExecution(
                        $this->deliveryRepository->find($delivery->id),
                        $deliveryExecution->getOriginalLtiLaunchParameters(),
                        $deliveryExecution->getLocale(),
                    );
                $this->deliveryExecutionService->deleteDeliveryExecution($deliveryExecutionToDelete);
            } catch (NotFoundHttpException $exception) {
                $this->logger->warning(
                    sprintf(
                        '[%s] Failed to find delivery execution after completed dry run, with message: %s',
                        $deliveryExecution->getId(),
                        $exception->getMessage(),
                    ),
                    compact('exception'),
                );
            } catch (Exception $exception) {
                $this->logger->warning(
                    sprintf(
                        '[%s] Failed to delete delivery execution after completed dry run, with message: %s',
                        $deliveryExecution->getId(),
                        $exception->getMessage(),
                    ),
                    compact('exception'),
                );
            }
        }
    }
}
