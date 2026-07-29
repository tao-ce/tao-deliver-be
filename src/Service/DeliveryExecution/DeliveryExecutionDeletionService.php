<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution;

use App\Service\DeliveryExecution\Contract\DeliveryExecutionDeletionServiceInterface;
use App\TestRunner\Service\ExternalTimerService;

readonly class DeliveryExecutionDeletionService implements DeliveryExecutionDeletionServiceInterface
{
    public function __construct(
        private DeliveryExecutionDeleter $deliveryExecutionDeleteService,
        private LoggerAwareDeliveryExecutionService $deliveryExecutionRepository,
        private DeliveryExecutionUploadsManagerService $deliveryExecutionUploadsManager,
        private DeliveryExecutionResultManagerService $deliveryExecutionResultManagerService,
        private ExternalTimerService $externalTimerService,
    ) {
    }

    public function removeDeliveryExecutionById(string $deliveryExecutionId): void
    {
        $deliveryExecution = $this->deliveryExecutionRepository->findDeliveryExecution($deliveryExecutionId);

        if ($deliveryExecution) {
            // If object exists remove all related data: timers, datastore records.
            // DeliveryExecutionDeleter already performs the external cleanup.
            $this->deliveryExecutionDeleteService->delete($deliveryExecution);
            $this->deliveryExecutionRepository->deleteDeliveryExecution($deliveryExecution);

            return;
        }

        // If deliveryExecution record is missing, still cleanup dangling external artifacts.
        $this->externalTimerService->deleteServerTimer($deliveryExecutionId);
        $this->deliveryExecutionUploadsManager->dropUploads($deliveryExecutionId);
        $this->deliveryExecutionResultManagerService->dropResults($deliveryExecutionId);
    }
}
