<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Lti\Event\AcsControlProcessedEvent;
use App\Messenger\Message\DeliveryExecutionAcsLogMessage;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener(AcsControlProcessedEvent::class, 'onAcsControlProcessed')]
class AcsControlLogSubscriber
{
    public function __construct(
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function onAcsControlProcessed(AcsControlProcessedEvent $acsControlProcessedEvent): void
    {
        $deliveryExecution = $acsControlProcessedEvent->deliveryExecution;
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $currentAssessmentItemRef = $testSession->getCurrentAssessmentItemRef();
        $itemId = null;
        if ($currentAssessmentItemRef !== false) {
            $itemId = $currentAssessmentItemRef->getIdentifier();
        }

        $this->messageBus->dispatch(new DeliveryExecutionAcsLogMessage(
            $deliveryExecution->getId(),
            $itemId,
            $acsControlProcessedEvent->status,
            $acsControlProcessedEvent->acsControl,
        ));
    }
}
