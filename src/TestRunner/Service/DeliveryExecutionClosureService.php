<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionStatus;
use Psr\Log\LoggerInterface;

class DeliveryExecutionClosureService
{
    public function __construct(
        private readonly LoggerInterface $auditPlatformLogger,
        private readonly TestSessionShutdownService $sessionShutdownService,
    ) {
    }

    public function close(DeliveryExecution $deliveryExecution): bool
    {
        if (!$deliveryExecution->isResultProcessable()) {
            $this->auditPlatformLogger->info(
                sprintf(
                    '[%s] Delivery execution\'s state does not permit result processing',
                    $deliveryExecution->getId(),
                ),
            );

            return false;
        }

        try {
            $this->sessionShutdownService->endTestSession($deliveryExecution, DeliveryExecutionStatus::STATUS_CLOSED);
            $this->auditPlatformLogger->info(
                sprintf(
                    '[%s] - Delivery execution has been finished automatically due to scheduled closure claim is provided',
                    $deliveryExecution->getId(),
                ),
            );
        } catch (TestSessionStateCollisionException) {
            $this->auditPlatformLogger->info(
                sprintf('[%s] Delivery execution is already finished', $deliveryExecution->getId()),
            );

            return false;
        }

        return true;
    }
}
