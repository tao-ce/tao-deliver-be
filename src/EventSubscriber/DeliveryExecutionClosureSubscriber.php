<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Messenger\Message\DeliveryExecutionClosureMessage;
use App\Messenger\MessageBus\PostProcessedMessageBusInterface;
use App\TestRunner\Event\DeliveryExecutionCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DeliveryExecutionClosureSubscriber implements EventSubscriberInterface
{
    public function __construct(private PostProcessedMessageBusInterface $postProcessedMessageBus)
    {
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
        if (!$deliveryExecution->isDryRun() && !$deliveryExecution->isReview() && $deliveryExecution->getCloseAt()) {
            /** Send closure message to PubSub, it will be consumed by external script */
            $this->postProcessedMessageBus->dispatch(
                new DeliveryExecutionClosureMessage(
                    $deliveryExecution->getId(),
                    $deliveryExecution->getCloseAt()->getTimestamp(),
                ),
            );
        }
    }
}
