<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\DeliveryExecution\ScoringEligibilityChecker;
use App\TestRunner\Event\DeliveryExecutionScoredEvent;
use OAT\Library\GraderMessengerBridge\Message\Event\DeliveryExecutionFinishedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ScoringEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ScoringEligibilityChecker $scorerEligibilityChecker,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $auditDeliveryExecutionLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DeliveryExecutionScoredEvent::class => 'onDeliveryExecutionScored',
        ];
    }

    public function onDeliveryExecutionScored(DeliveryExecutionScoredEvent $event): void
    {
        $deliveryExecution = $event->getDeliveryExecution();

        if (!$this->scorerEligibilityChecker->isEligible($deliveryExecution)) {
            return;
        }

        $message = new DeliveryExecutionFinishedEvent(
            $deliveryExecution->getTenantId(),
            $deliveryExecution->getDeliveryId(),
            $deliveryExecution->getOriginalId(), // TODO allow to score individual snapshots once the other app add support for multiple attempts
            $deliveryExecution->getLtiLaunchParameters()['user_id'],
            [],
            $deliveryExecution->getLtiLaunchParameters()['client_id'] ?? null,
            $deliveryExecution->getLtiLaunchParameters()['platform_issuer'] ?? null,
            $deliveryExecution->getLtiLaunchParameters()['ags_claim'] ?? null,
            $deliveryExecution->getAttempt(),
            $deliveryExecution->getLocale(),
        );

        $this->messageBus->dispatch($message);

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] Successfully dispatched DeliveryExecutionFinishedEvent',
                $message->getDeliveryExecutionId(),
            ),
        );
    }
}
