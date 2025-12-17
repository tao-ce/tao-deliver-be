<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DataStore\EventSubscriber;

use App\Messenger\Message\DataStoreDeliveryExecutionActionMessage;
use App\Messenger\MessageBus\PostProcessedMessageBusInterface;
use App\Messenger\Stamp\StartTimeStamp;
use App\Messenger\Stamp\TypeStamp;
use App\TestRunner\Event\DeliveryExecutionCreatedEvent;
use App\Environment\FeatureFlagAdapterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DataStoreEventSubscriber implements EventSubscriberInterface
{
    private const DATA_STORE_ENABLE_RESULTS_TRANSFER = 'DATA_STORE_ENABLE_RESULTS_TRANSFER';

    public function __construct(
        private readonly PostProcessedMessageBusInterface $postProcessedMessageBus,
        private readonly LoggerInterface $auditDeliveryExecutionLogger,
        private readonly FeatureFlagAdapterInterface $featureFlagAdapter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DeliveryExecutionCreatedEvent::class => 'onDeliveryExecutionCreated',
        ];
    }

    public function onDeliveryExecutionCreated(DeliveryExecutionCreatedEvent $event): void
    {
        $deliveryExecution = $event->getDeliveryExecution();

        if ($deliveryExecution->isDryRun()) {
            return;
        }

        $dataStoreResultsTransferEnabled = $this->featureFlagAdapter->isEnabled(
            $deliveryExecution->getTenantId(),
            self::DATA_STORE_ENABLE_RESULTS_TRANSFER,
        );

        if (!$dataStoreResultsTransferEnabled) {
            return;
        }

        $message = new DataStoreDeliveryExecutionActionMessage(
            $deliveryExecution,
            DataStoreDeliveryExecutionActionMessage::ACTION_CREATE,
        );

        $this->postProcessedMessageBus->dispatch($message, [
            new TypeStamp(DataStoreDeliveryExecutionActionMessage::ACTION_CREATE),
            new StartTimeStamp($deliveryExecution->getStartedAt()->getTimestamp()),
        ]);

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] DataStore transfer message a Delivery Execution initialization',
                $message->getDeliveryExecutionId(),
            ),
        );
    }
}
