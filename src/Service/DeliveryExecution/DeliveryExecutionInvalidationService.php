<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution;

use App\DataStore\Sender\DataStoreSenderInterface;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\Invalidation;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use Psr\Log\LoggerInterface;

class DeliveryExecutionInvalidationService
{
    public function __construct(
        private readonly DeliveryExecutionServiceInterface $deliveryExecutionService,
        private readonly DataStoreSenderInterface $dataStoreSender,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function invalidate(DeliveryExecution $deliveryExecution, string $userLogin): void
    {
        $invalidation = Invalidation::create($userLogin);
        $deliveryExecution->setinvalidation($invalidation);

        $this->deliveryExecutionService->saveDeliveryExecution($deliveryExecution);

        $this->logger->info(
            sprintf(
                '[%s] Delivery execution result invalidated by user %s',
                $deliveryExecution->getId(),
                $userLogin,
            ),
        );

        $this->triggerDataStoreSync($deliveryExecution);
    }

    public function triggerDataStoreSync(DeliveryExecution $deliveryExecution): void
    {
        $this->dataStoreSender->send($deliveryExecution);

        $this->logger->info(
            sprintf(
                '[%s] DataStore synchronization triggered for invalidated delivery execution',
                $deliveryExecution->getId(),
            ),
        );
    }
}
