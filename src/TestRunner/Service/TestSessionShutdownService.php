<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionStatus;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Event\DeliveryExecutionClosedEvent;
use App\TestRunner\Event\TestSessionEndEvent;
use App\TestRunner\Event\TestSessionInteractionEvent;
use JsonException;
use Psr\Log\LoggerInterface;
use qtism\common\datatypes\files\FileManagerException;
use qtism\runtime\common\State;
use qtism\runtime\pci\json\UnmarshallingException;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\AssessmentTestSessionException;
use qtism\runtime\tests\AssessmentTestSessionState;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class TestSessionShutdownService
{
    protected const ACTION_NAME = 'terminated';

    public function __construct(
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly ItemSessionService $itemSessionService,
        private readonly LoggerInterface $logger,
        private readonly RepositoryAwareDeliveryExecutionServiceInterface $deliveryExecutionService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function endTestSession(
        DeliveryExecution $deliveryExecution,
        DeliveryExecutionStatus $targetStatus,
    ): AssessmentTestSession {
        if ($targetStatus->equals($deliveryExecution->getStatus())) {
            throw new TestSessionStateCollisionException(
                sprintf(
                    '[%s] - Process try to close session with [%s] status, but a test delivery execution is already [%s]',
                    $deliveryExecution->getId(),
                    $deliveryExecution->getStatus(),
                    $deliveryExecution->getStatus(),
                ),
            );
        }

        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        if ($testSession->isRunning()) {
            $this->storeTemporaryItemStateToRegularItemState($deliveryExecution, $testSession);
            // This ensures that outcome processing gets executed even when no item responses at all were added
            if (
                $testSession->getCurrentAssessmentItemSession()->getState() === AssessmentTestSessionState::INTERACTING
            ) {
                try {
                    $testSession->endAttempt(new State(), true);
                } catch (AssessmentTestSessionException) {
                }
            }
            $testSession->endTestSession();
            $this->deliveryExecutionPropertyService->persistTestSession($testSession);
        }

        $deliveryExecution->close($targetStatus->value);
        $this->eventDispatcher->dispatch(new DeliveryExecutionClosedEvent($deliveryExecution));
        $this->deliveryExecutionService->saveDeliveryExecution($deliveryExecution);

        $this->eventDispatcher->dispatch(
            new TestSessionEndEvent(self::class, $deliveryExecution),
        );
        $this->eventDispatcher->dispatch(
            new TestSessionInteractionEvent(self::class, static::ACTION_NAME, $deliveryExecution, $testSession),
        );

        return $testSession;
    }

    /**
     * @throws UnmarshallingException
     * @throws FileManagerException
     * @throws JsonException
     */
    private function storeTemporaryItemStateToRegularItemState(
        DeliveryExecution $deliveryExecution,
        AssessmentTestSession $testSession,
    ): void {
        $itemIdentifier = $testSession->getCurrentAssessmentItemRef()?->getIdentifier();
        $itemState = $deliveryExecution->getExtraStateData()->getTemporaryItemStates()[$itemIdentifier] ?? null;

        if (null === $itemState) {
            return;
        }

        try {
            $this->itemSessionService->submitResponse(
                $deliveryExecution,
                $this->transformItemStateToResponse($itemState),
                0,
                $itemState,
            );
        } catch (AssessmentTestSessionException $exception) {
            if ($exception->getCode() === AssessmentTestSessionException::UNKNOWN) {
                $this->logger->critical($exception->getMessage(), compact('exception'));
            }
        }
    }

    /**
     * @throws JsonException
     */
    private function transformItemStateToResponse(string $itemState): array
    {
        $responses = [];
        foreach (json_decode($itemState, true, 512, JSON_THROW_ON_ERROR) as $variable => $state) {
            if (isset($state['response'])) {
                $responses[$variable] = $state['response'];
            }
        }

        return $responses;
    }
}
