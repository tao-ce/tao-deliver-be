<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\EventSubscriber;

use App\TestRunner\Event\DeliveryExecutionClosedEvent;
use App\TestRunner\Service\ExternalTimerService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class DeliveryExecutionClosedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ExternalTimerService $timerService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DeliveryExecutionClosedEvent::class => 'onDeliveryExecutionClosed',
        ];
    }

    public function onDeliveryExecutionClosed(DeliveryExecutionClosedEvent $event): void
    {
        $deliveryExecution = $event->getDeliveryExecution();
        $timerDefinition = $this->timerService->getServerTimer($deliveryExecution);

        if ($timerDefinition) {
            $deliveryExecution->addExternalTimerDefinition($timerDefinition);
        }
    }
}
