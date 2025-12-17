<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use OAT\Bundle\QtiBundle\Factory\VariableStateFactory;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\AssessmentTestSessionException;

readonly class ItemSessionService
{
    public function __construct(
        private VariableStateFactory $variableStateFactory,
        private DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private TimerService $timerService,
    ) {
    }

    /**
     * @thorws AssessmentTestSessionException
     */
    public function beginAttempt(DeliveryExecution $deliveryExecution): void
    {
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $this->timerService->startServerTimer($deliveryExecution, $testSession);
        $testSession->beginAttempt(true);
    }

    /**
     * @throws AssessmentTestSessionException
     */
    public function submitResponse(
        DeliveryExecution $deliveryExecution,
        array $response,
        float $duration,
        ?string $itemState = null,
    ): void {
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $this->timerService->endServerTimer($deliveryExecution, $testSession, $duration);
        $this->submitState($deliveryExecution, $testSession, $response, $itemState);
    }

    private function submitState(
        DeliveryExecution $deliveryExecution,
        AssessmentTestSession $testSession,
        array $response,
        ?string $itemState,
    ): void {
        $testSession->endAttempt(
            $this->variableStateFactory->createStateWithItemResponses($testSession, $response),
            true,
        );
        $this->addItemState($deliveryExecution, $testSession, $itemState);
    }

    private function addItemState(
        DeliveryExecution $deliveryExecution,
        AssessmentTestSession $testSession,
        ?string $itemState,
    ) {
        if (null === $itemState) {
            return;
        }

        $deliveryExecution->addItemState(
            $testSession->getCurrentAssessmentItemRef()->getIdentifier(),
            $itemState,
        );
    }
}
