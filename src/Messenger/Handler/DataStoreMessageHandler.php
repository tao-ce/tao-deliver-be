<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\DataStore\Sender\DataStoreSenderInterface;
use App\Messenger\Message\DataStoreMessage;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @deprecated
 */
#[AsMessageHandler]
class DataStoreMessageHandler
{
    public function __construct(
        private RepositoryAwareDeliveryExecutionServiceInterface $loggerAwareDeliveryExecutionService,
        private DataStoreSenderInterface $dataStoreResultsSender,
    ) {
    }

    public function __invoke(DataStoreMessage $message): void
    {
        $deliveryExecution = $this->loggerAwareDeliveryExecutionService->findDeliveryExecution(
            $message->getDeliveryExecutionId(),
        );
        if (!$deliveryExecution) {
            return;
        }

        $this->dataStoreResultsSender->send($deliveryExecution);
    }
}
