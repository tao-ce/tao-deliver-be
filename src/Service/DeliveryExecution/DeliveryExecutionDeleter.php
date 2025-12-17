<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Messenger\Message\DataStoreDeliveryExecutionActionMessage;
use App\Messenger\Stamp\StartTimeStamp;
use App\Repository\DeliveryExecutionRepository;
use App\Service\DeliveryExecution\Exception\DeliveryExecutionDeletionException;
use App\TestRunner\Service\ExternalTimerService;
use Exception;
use Symfony\Component\Messenger\MessageBusInterface;

class DeliveryExecutionDeleter
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly DeliveryExecutionRepository $deliveryExecutionRepository,
        private readonly DeliveryExecutionResultManagerService $deliveryExecutionResultManagerService,
        private readonly ExternalTimerService $externalTimerService,
    ) {
    }

    // Deletes all remote entities linked to a delivery executions
    public function deleteRelatedEntities(DeliveryExecution $deliveryExecution): void
    {
        try {
            $this->deliveryExecutionResultManagerService->dropResults($deliveryExecution->getId());
            $this->externalTimerService->deleteServerTimer($deliveryExecution);
        } catch (Exception $exception) {
            $this->throw($deliveryExecution, $exception);
        }
    }

    // Deletes all remote entities linked to a delivery executions and publishes a notification to the Pub/Sub topic
    public function deleteRemoteEntities(DeliveryExecution $deliveryExecution): void
    {
        try {
            $this->deleteRelatedEntities($deliveryExecution);
            $this->messageBus->dispatch(
                new DataStoreDeliveryExecutionActionMessage(
                    $deliveryExecution,
                    DataStoreDeliveryExecutionActionMessage::ACTION_DELETE,
                ),
                [new StartTimeStamp($deliveryExecution->getStartedAt()->getTimestamp())],
            );
        } catch (Exception $exception) {
            $this->throw($deliveryExecution, $exception);
        }
    }

    public function delete(DeliveryExecution $deliveryExecution): void
    {
        try {
            $this->deliveryExecutionRepository->save($deliveryExecution->setIsDeleted());
            $this->deleteRemoteEntities($deliveryExecution);
        } catch (Exception $exception) {
            $this->throw($deliveryExecution, $exception);
        }
    }

    private function throw(DeliveryExecution $deliveryExecution, Exception $exception): void
    {
        throw $exception instanceof DeliveryExecutionDeletionException
            ? $exception
            : new DeliveryExecutionDeletionException(
                sprintf(
                    'Failed to delete delivery execution with id "%s". Reason: %s',
                    $deliveryExecution->getId(),
                    $exception->getMessage(),
                ),
                $exception->getCode(),
                $exception,
            );
    }
}
