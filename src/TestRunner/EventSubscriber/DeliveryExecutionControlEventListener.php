<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\EventSubscriber;

use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorIdentity;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionControlAction;
use App\Messenger\Message\DeliveryExecution\ExecutionControlMessage;
use App\Qti\Extractor\QtiVariableExtractor;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Event\Control\DeliveryExecutionControlEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener(DeliveryExecutionControlEvent::class, 'onExecutionControl')]
class DeliveryExecutionControlEventListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly MessageBusInterface $messageBus,
        private readonly QtiVariableExtractor $qtiVariableExtractor,
    ) {
    }

    public function onExecutionControl(DeliveryExecutionControlEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $deliveryExecution = $event->getDeliveryExecution();
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $route = $testSession->getRoute();
        $testOutcomeVariables = $event->controlType->isSecurityEvent()
            ? null
            : $this->qtiVariableExtractor->extractTestOutcomeVariables($deliveryExecution);

        $this->messageBus->dispatch(
            new ExecutionControlMessage(
                new DeliveryExecutionActorIdentity(
                    $deliveryExecution->getUserId(),
                    $deliveryExecution->getLtiLaunchParameters()['user_name'] ?? '',
                    $event->deliveryExecutionActorRole,
                    $request?->headers->get('User-Agent'),
                    $request?->getClientIp(),
                ),
                new DeliveryExecutionControlAction(
                    $event->controlType,
                    $event->controlStatus,
                ),
                $event->timestamp,
                $deliveryExecution,
                $route->valid() ? $route->current()->getAssessmentItemRef()->getIdentifier() : null,
                $event->controlReason,
                $testOutcomeVariables,
            ),
        );
    }
}
