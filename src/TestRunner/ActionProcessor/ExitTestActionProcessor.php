<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Event\DeliveryExecutionClosedEvent;
use App\TestRunner\Event\TestSessionInteractionEvent;
use App\TestRunner\Service\ItemSessionService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ExitTestActionProcessor extends AbstractActionProcessor
{
    public const ACTION_NAME = 'exitTest';

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly ItemSessionService $itemSessionService,
        private readonly LoggerInterface $auditDeliveryExecutionLogger,
    ) {
    }

    public function getActionName(): string
    {
        return self::ACTION_NAME;
    }

    public function process(DeliveryExecution $deliveryExecution, array $actionParameters): array
    {
        $parameters = $actionParameters['parameters'];
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        $this->itemSessionService->submitResponse(
            $deliveryExecution,
            json_decode($parameters['itemResponse'], true),
            (float)$parameters['itemDuration'],
        );

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - test taker has ended the Test: %s',
                $deliveryExecution->getId(),
                $testSession->getAssessmentTest()->getIdentifier(),
            ),
        );

        $testSession->endTestSession();

        $this->deliveryExecutionPropertyService->persistTestSession($testSession);

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - test taker responses were saved for Test: %s',
                $deliveryExecution->getId(),
                $testSession->getAssessmentTest()->getIdentifier(),
            ),
        );

        $deliveryExecution->close();
        $this->eventDispatcher->dispatch(new DeliveryExecutionClosedEvent($deliveryExecution));

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - test taker test was saved with the status closed for Test: %s',
                $deliveryExecution->getId(),
                $testSession->getAssessmentTest()->getIdentifier(),
            ),
        );

        $this->eventDispatcher->dispatch(
            new TestSessionInteractionEvent(self::class, $this->getActionName(), $deliveryExecution, $testSession),
        );

        return $this->getActionProcessorResponse($actionParameters, []);
    }
}
