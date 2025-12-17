<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\Dto\DeliveryExecutionDto;
use App\TestRunner\Event\DeliveryExecutionCreatedEvent;
use App\TestRunner\Event\TestSessionEndEvent;
use App\TestRunner\Event\TestSessionInteractionEvent;
use App\TestRunner\Service\DeliveryExecutionClosureService;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemReader;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class SynchronizeDeliveryExecutionService
{
    protected const ACTION_NAME = 'terminated';

    public function __construct(
        private readonly DeliveryExecutionFactory $deliveryExecutionFactory,
        private readonly DeliveryExecutionService $deliveryExecutionService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly MessageBusInterface $messageBus,
        private readonly FilesystemReader $qtiCompiledDeliveriesStorage,
        private readonly DeliveryExecutionClosureService $deliveryExecutionClosureService,
        private readonly LoggerInterface $auditDeliveryExecutionLogger,
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
    ) {
    }

    /**
     * @throws FilesystemException
     * @throws InvalidArgumentException
     */
    public function synchronize(
        DeliveryExecutionDto $deliveryExecutionDto,
        string $tenantId,
    ): void {
        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] Synchronizing DeliveryExecution',
                $deliveryExecutionDto->deliverExecutionId,
            ),
        );
        $deliveryExecution = $this->deliveryExecutionFactory->createFromDeliveryExecutionDto(
            $deliveryExecutionDto,
        );

        $this->validateDeliveryExecution($deliveryExecution, $tenantId);
        $this->dispatchDeliveryExecutionEvents($deliveryExecution);

        if ($this->deliveryExecutionClosureService->close($deliveryExecution)) {
            return;
        }

        $this->deliveryExecutionService->saveDeliveryExecution($deliveryExecution);
        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] DeliveryExecution successfully stored',
                $deliveryExecutionDto->deliverExecutionId,
            ),
        );

        if ($deliveryExecution->isStateFinal()) {
            $this->auditDeliveryExecutionLogger->info(
                sprintf(
                    '[%s] DeliveryExecution `TestSessionEndEvent` triggered',
                    $deliveryExecutionDto->deliverExecutionId,
                ),
            );
            $this->eventDispatcher->dispatch(
                new TestSessionEndEvent(self::class, $deliveryExecution),
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws FilesystemException
     */
    private function validateDeliveryExecution(DeliveryExecution $deliveryExecution, string $tenantId): void
    {
        if ($deliveryExecution->getTenantId() !== $tenantId) {
            throw new InvalidArgumentException(
                sprintf(
                    '[%s][%s] DeliveryExecution does not belong to the tenant',
                    $deliveryExecution->getId(),
                    $tenantId,
                ),
            );
        }

        $pathToDeliveryCompactFile = $deliveryExecution->getQtiCompactTestFilePath();
        if (!$this->qtiCompiledDeliveriesStorage->has($pathToDeliveryCompactFile)) {
            throw new InvalidArgumentException(
                sprintf(
                    '[%s] Compiled delivery does not exist',
                    $deliveryExecution->getDeliveryId(),
                ),
            );
        }

        if ($deliveryExecution->isStateFinal() && $deliveryExecution->getFinishedAt() === null) {
            $this->eventDispatcher->dispatch(
                new TestSessionInteractionEvent(self::class, static::ACTION_NAME, $deliveryExecution, $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution)),
            );

            throw new InvalidArgumentException(
                sprintf(
                    '[%s] DeliveryExecution is final but has no end date',
                    $deliveryExecution->getId(),
                ),
            );
        }
    }

    private function dispatchDeliveryExecutionEvents(DeliveryExecution $deliveryExecution): void
    {
        $this->eventDispatcher->dispatch(
            new DeliveryExecutionCreatedEvent($deliveryExecution),
        );

        if ($deliveryExecution->hasUiEvents()) {
            $deliveryExecutionUIEventMessage = $deliveryExecution->popAllUiEvents();
            $this->messageBus->dispatch($deliveryExecutionUIEventMessage);
            $this->auditDeliveryExecutionLogger->info(
                sprintf(
                    '[%s] - received %u UI events',
                    $deliveryExecution->getId(),
                    count($deliveryExecutionUIEventMessage->getEvents()),
                ),
            );
        }

        if ($deliveryExecution->hasAssessmentEvents()) {
            $assessmentEvents = $deliveryExecution->popAllAssessmentEvents();
            $numberOfAssessmentEvents = 0;
            foreach ($assessmentEvents as $assessmentEvent) {
                $this->messageBus->dispatch($assessmentEvent);
                $numberOfAssessmentEvents++;
            }
            $this->auditDeliveryExecutionLogger->info(
                sprintf(
                    '[%s] - received %u assessment-control events',
                    $deliveryExecution->getId(),
                    $numberOfAssessmentEvents,
                ),
            );
        }
    }
}
