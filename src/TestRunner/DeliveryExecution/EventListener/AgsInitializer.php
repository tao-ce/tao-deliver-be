<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\DeliveryExecution\EventListener;

use App\Lti\LtiCustomSettings;
use App\Service\Ags\AgsInitializationService;
use App\TestRunner\Event\DeliveryExecutionCreatedEvent;
use App\TestRunner\Event\DeliveryExecutionLaunchEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(DeliveryExecutionCreatedEvent::class, 'onDeliveryExecutionCreated')]
#[AsEventListener(DeliveryExecutionLaunchEvent::class, 'onDeliveryExecutionLaunched')]
class AgsInitializer
{
    private bool $isDeliveryExecutionBeingCreated = false;

    public function __construct(
        private readonly AgsInitializationService $agsInitializationService,
        private readonly LtiCustomSettings $ltiCustomSettings,
    ) {
    }

    public function onDeliveryExecutionCreated(): void
    {
        $this->isDeliveryExecutionBeingCreated = true;
    }

    public function onDeliveryExecutionLaunched(DeliveryExecutionLaunchEvent $event): void
    {
        $deliveryExecution = $event->getDeliveryExecution();
        $parameters = $deliveryExecution->getLtiLaunchParameters();
        if (
            empty($parameters['ags_claim'])
            || $this->ltiCustomSettings->isDryRunEnabled($parameters)
            || $deliveryExecution->isReview()
            || !$this->ltiCustomSettings->isResetEnabled($parameters)
            && $this->ltiCustomSettings->isForceResumeModeEnabled($parameters)
            && !$this->isDeliveryExecutionBeingCreated
        ) {
            return;
        }
        $this->isDeliveryExecutionBeingCreated = false;

        $this->agsInitializationService->init($deliveryExecution);
    }
}
