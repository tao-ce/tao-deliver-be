<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message\DeliveryExecution;

use App\TestRunner\Event\DeliveryExecutionAwareEventInterface;
use DateTimeInterface;
use InvalidArgumentException;

abstract class AbstractDeliveryExecutionMessage
{
    public function __construct(
        private string $id,
        private string $deliveryId,
        private string $tenantId,
        private string $status,
        private array $ltiLaunchParameters,
        private DateTimeInterface $startedAt,
        private ?DateTimeInterface $finishedAt = null,
        private ?string $locale = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDeliveryId(): string
    {
        return $this->deliveryId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getLtiLaunchParameters(): array
    {
        return $this->ltiLaunchParameters;
    }

    public function getStartedAt(): DateTimeInterface
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?DateTimeInterface
    {
        return $this->finishedAt;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public static function createFromEvent(DeliveryExecutionAwareEventInterface $event): static
    {
        if (!in_array($event::class, static::getSupportedEventFQCNs(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid event type provided to initialize "%s". Expected: "%s", received: "%s"',
                static::class,
                implode(', ', static::getSupportedEventFQCNs()),
                $event::class,
            ));
        }

        $deliveryExecution = $event->getDeliveryExecution();

        return new static(
            $deliveryExecution->getId(),
            $deliveryExecution->getDeliveryId(),
            $deliveryExecution->getTenantId(),
            $deliveryExecution->getStatus(),
            $deliveryExecution->getLtiLaunchParameters(),
            $deliveryExecution->getStartedAt(),
            $deliveryExecution->getFinishedAt(),
            $deliveryExecution->getUserSelectedLocale(),
        );
    }

    abstract protected static function getSupportedEventFQCNs(): array;
}
