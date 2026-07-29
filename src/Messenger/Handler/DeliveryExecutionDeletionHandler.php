<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Messenger\Message\DataPolicy\ConfirmationMessage;
use App\Messenger\Message\DataPolicy\RemovalRequestMessage;
use App\Messenger\Message\DataPolicy\RemovalConfirmationMessage;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionDeletionServiceInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class DeliveryExecutionDeletionHandler
{
    public function __construct(
        private DeliveryExecutionDeletionServiceInterface $deliveryExecutionDeletionService,
        private LoggerInterface $auditPlatformLogger,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(RemovalRequestMessage $message): void
    {
        //
        // at queue we have only process messages from our app
        //
        if ($message->ownerApp !== ConfirmationMessage::DEFAULT_OWNER_APP) {
            return;
        }

        $errors = [];

        foreach ($message->deliveryExecutionIds as $deliveryExecutionId) {
            try {
                $this->deliveryExecutionDeletionService->removeDeliveryExecutionById($deliveryExecutionId);
                $this->auditPlatformLogger->info(sprintf(
                    'DeliveryExecution %s deleted for cleanup message type %s',
                    $deliveryExecutionId,
                    $message->type,
                ));
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();

                $this->auditPlatformLogger->error(sprintf(
                    'DeliveryExecution %s failed to delete for cleanup message type %s; Error: %s',
                    $deliveryExecutionId,
                    $message->type,
                    $e->getMessage(),
                ));
            }
        }

        $status = empty($errors) ? ConfirmationMessage::STATUS_REMOVED : ConfirmationMessage::STATUS_FAILED;
        $confirmationMessage = RemovalConfirmationMessage::createRemovalConfirmationMessage($message, $status, $errors);

        $this->messageBus->dispatch($confirmationMessage);
    }
}
