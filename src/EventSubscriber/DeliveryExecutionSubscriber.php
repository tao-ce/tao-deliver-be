<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Messenger\Message\DeliveryExecution\DeliveryExecutionCreatedMessage;
use App\Messenger\MessageBus\PostProcessedMessageBusInterface;
use App\Repository\DeliveryExecutionAlias\Contract\DeliveryExecutionIdentifierAliasRepositoryInterface;
use App\Repository\DeliveryExecutionRepository;
use App\TestRunner\Event\DeliveryExecutionCreatedEvent;
use App\TestRunner\Event\DeliveryExecutionPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

class DeliveryExecutionSubscriber implements EventSubscriberInterface
{
    private bool $isDeliveryExecutionBeingCreated = false;

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly PostProcessedMessageBusInterface $postProcessedMessageBus,
        private readonly DeliveryExecutionIdentifierAliasRepositoryInterface $deliveryExecutionExternalAliasRepository,
        private readonly LtiCustomSettings $ltiCustomSettings,
        private readonly DeliveryExecutionRepository $deliveryExecutionRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DeliveryExecutionCreatedEvent::class => 'onDeliveryExecutionCreated',
            DeliveryExecutionPersistedEvent::class => 'onDeliveryExecutionPersisted',
        ];
    }

    public function onDeliveryExecutionCreated(DeliveryExecutionCreatedEvent $deliveryExecutionCreatedEvent): void
    {
        $this->isDeliveryExecutionBeingCreated = true;
        $this->validateExternalAlias($deliveryExecutionCreatedEvent->getDeliveryExecution());
    }

    public function onDeliveryExecutionPersisted(DeliveryExecutionPersistedEvent $event): void
    {
        $this->postProcessedMessageBus->free();

        if ($this->isDeliveryExecutionBeingCreated) {
            $this->messageBus->dispatch(DeliveryExecutionCreatedMessage::createFromEvent($event));
            $this->resolveExternalAlias($event->getDeliveryExecution());
        }
    }

    private function validateExternalAlias(DeliveryExecution $deliveryExecution): void
    {
        if ($deliveryExecution->isDryRun() || $deliveryExecution->isReview()) {
            return;
        }
        $alias = $this->ltiCustomSettings->getDeliverExecutionIdAlias($deliveryExecution->getLtiLaunchParameters());
        if ($alias !== null) {
            $existingDeliveryExecutionId = $this->deliveryExecutionExternalAliasRepository->findDeliveryExecutionId(
                $deliveryExecution->getTenantId(),
                $alias,
            );
            if (
                $existingDeliveryExecutionId === null
                || $existingDeliveryExecutionId === $deliveryExecution->getId()
            ) {
                return;
            }

            throw new BadRequestHttpException(
                sprintf(
                    'Delivery execution with alias "%s" already exists.',
                    $alias,
                ),
            );
        }
    }

    private function resolveExternalAlias(DeliveryExecution $deliveryExecution): void
    {
        if ($deliveryExecution->isDryRun() || $deliveryExecution->isReview()) {
            return;
        }
        $alias = $this->ltiCustomSettings->getDeliverExecutionIdAlias($deliveryExecution->getLtiLaunchParameters());
        if ($alias !== null) {
            $alias = $this->deliveryExecutionExternalAliasRepository->saveDeliveryExecutionId(
                $deliveryExecution->getTenantId(),
                $alias,
                $deliveryExecution->getId(),
            );

            $deliveryExecution->setExternalAliasId($alias->getId());
            $this->deliveryExecutionRepository->save($deliveryExecution);
        }
    }
}
