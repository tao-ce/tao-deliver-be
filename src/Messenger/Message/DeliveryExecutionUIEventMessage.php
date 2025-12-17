<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\TestRunner\Event\DeliveryExecutionAwareEventInterface;

class DeliveryExecutionUIEventMessage extends AbstractDeliveryExecutionAwareMessage implements DeliveryExecutionAwareEventInterface
{
    public function __construct(private readonly DeliveryExecution $deliveryExecution, private readonly array $events)
    {
        parent::__construct($this->deliveryExecution->getId());
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function getDeliveryExecution(): DeliveryExecution
    {
        return $this->deliveryExecution;
    }

    public function getDeliveryId(): string
    {
        return $this->deliveryExecution->getDeliveryId();
    }

    public function getTenantId(): string
    {
        return $this->deliveryExecution->getTenantId();
    }

    public function getBatteryId(): ?string
    {
        return $this->deliveryExecution->getBatteryId();
    }
}
