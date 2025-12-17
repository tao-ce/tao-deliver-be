<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Delivery\Event\DeliveryCreatedEvent;
use App\Service\Delivery\ScoringEligibilityChecker;
use OAT\Library\GraderMessengerBridge\Message\Event\DeliveryPublishedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class DeliveryEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ScoringEligibilityChecker $scorerEligibilityChecker,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $auditPlatformLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DeliveryCreatedEvent::class => 'onDeliveryCreated',
        ];
    }

    public function onDeliveryCreated(DeliveryCreatedEvent $event): void
    {
        $delivery = $event->getDelivery();

        if (!$this->scorerEligibilityChecker->isEligible($delivery)) {
            return;
        }

        $message = new DeliveryPublishedEvent(
            $delivery->getTenantId(),
            $delivery->getId(),
            $delivery->getConfiguration(),
            $delivery->getMainLocale(),
        );

        $this->messageBus->dispatch($message);

        $this->auditPlatformLogger->info(
            sprintf(
                '[%s] Successfully dispatched DeliveryPublishedEvent',
                $message->getDeliveryId(),
            ),
        );
    }
}
