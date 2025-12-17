<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorIdentity;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorRole;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionControlAction;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionControlReason;
use App\TestRunner\Event\Control\ControlType;
use App\TestRunner\Event\DeliveryExecutionAwareEventInterface;
use Carbon\CarbonInterface;
use JsonSerializable;

class ExecutionControlMessage implements JsonSerializable, DeliveryExecutionAwareEventInterface
{
    public function __construct(
        private readonly DeliveryExecutionActorIdentity $actor,
        private readonly DeliveryExecutionControlAction $controlAction,
        private readonly CarbonInterface $actionTime,
        private readonly DeliveryExecution $deliveryExecution,
        private readonly ?string $itemId,
        private readonly ?DeliveryExecutionControlReason $reason,
        private readonly ?iterable $testOutcomes = null,
    ) {
    }

    public function getActorRole(): DeliveryExecutionActorRole
    {
        return $this->actor->getRole();
    }

    public function getControlType(): ControlType
    {
        return $this->controlAction->getControlType();
    }

    public function getDeliveryExecution(): DeliveryExecution
    {
        return $this->deliveryExecution;
    }

    public function jsonSerialize(): array
    {
        return [
            'actorIdentity' => $this->actor,
            'action' => $this->controlAction,
            'timestamp' => $this->actionTime->getTimestampMs(),
            'deliveryExecution' => [
                'id' => $this->deliveryExecution->getId(),
                'status' => $this->deliveryExecution->getStatus(),
            ],
            'resourceLink' => [
                'identifier' => $this->deliveryExecution->getLtiLaunchParameters()['resource_link_id'] ?? '',
            ],
            'itemId' => $this->itemId,
            'reason' => $this->reason,
            'testOutcomes' => iterator_to_array($this->testOutcomes ?? []),
        ];
    }
}
