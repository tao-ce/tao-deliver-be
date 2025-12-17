<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Event\TestSessionStartEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\AssessmentTestSessionState;

class TestSessionInitiator
{
    public function __construct(
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function init(DeliveryExecution $deliveryExecution, bool $force = false): void
    {
        // TODO find out how and why a delivery execution can go back into `initial` state
        if ($deliveryExecution->isStateInitial()) {
            $deliveryExecution->start();
        }

        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        if ($force || $testSession->getState() === AssessmentTestSessionState::INITIAL) {
            $this->startQtiSession($deliveryExecution);
            $deliveryExecution->start();
            $this->eventDispatcher->dispatch(new TestSessionStartEvent($deliveryExecution));
        }
    }

    public function startQtiSession(DeliveryExecution $deliveryExecution): void
    {
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        $testSession->setConfig(AssessmentTestSession::INITIALIZE_ALL_ITEMS);
        $testSession->getRoute()->rewind();
        $testSession->beginTestSession();
        if ($testSession->getCurrentAssessmentItemSession()->getRemainingAttempts() !== 0) {
            $testSession->beginAttempt(true);
        }

        $this->deliveryExecutionPropertyService->persistTestSession($testSession);
    }
}
