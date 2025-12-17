<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Psr\Log\LoggerInterface;

class LoggerAwareDeliveryExecutionService implements RepositoryAwareDeliveryExecutionServiceInterface
{
    public function __construct(
        private DeliveryExecutionServiceInterface $deliveryExecutionService,
        private LoggerInterface $auditPlatformLogger,
    ) {
    }

    public function findDeliveryExecution(string $deliveryExecutionId): ?DeliveryExecution
    {
        $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecution($deliveryExecutionId);

        if (!$deliveryExecution) {
            $message = sprintf('[%s] Delivery execution was not found', $deliveryExecutionId);
            $this->auditPlatformLogger->warning($message);
        } else {
            $message = sprintf('[%s] Delivery execution was found', $deliveryExecution->getId());
            $this->auditPlatformLogger->info($message);
        }


        return $deliveryExecution;
    }

    public function saveDeliveryExecution(DeliveryExecution $deliveryExecutionModel): void
    {
        $this->deliveryExecutionService->saveDeliveryExecution($deliveryExecutionModel);
        $this->auditPlatformLogger->info(sprintf('[%s] Delivery execution was saved', $deliveryExecutionModel->getId()));
    }

    public function deleteDeliveryExecution(DeliveryExecution $deliveryExecutionModel): void
    {
        $this->deliveryExecutionService->deleteDeliveryExecution($deliveryExecutionModel);
        $this->auditPlatformLogger->info(
            sprintf('[%s] Delivery execution was deleted', $deliveryExecutionModel->getId()),
        );
    }

    public function findDeliveryExecutionOrFail(string $deliveryExecutionId): DeliveryExecution
    {
        try {
            $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecutionOrFail($deliveryExecutionId);
            $this->auditPlatformLogger->info(sprintf('[%s] Delivery execution was found', $deliveryExecutionId));
        } catch (DocumentNotFoundException $e) {
            $this->auditPlatformLogger->info(sprintf('[%s] Delivery execution was not found', $deliveryExecutionId));
            throw $e;
        }
        return $deliveryExecution;
    }
}
