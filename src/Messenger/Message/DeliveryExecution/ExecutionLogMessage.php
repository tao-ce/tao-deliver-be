<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorIdentity;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorRole;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionControlReason;
use App\TestRunner\Event\DeliveryExecutionAwareEventInterface;
use Carbon\CarbonInterface;
use JsonSerializable;

class ExecutionLogMessage implements JsonSerializable, DeliveryExecutionAwareEventInterface
{
    public const SYSTEM_REASON_OTHER_CODE = 40999;
    public const DEFAULT_ACTION_TYPE = 'log';

    public function __construct(
        private readonly DeliveryExecutionActorRole $issuer,
        private readonly DeliveryExecutionActorIdentity $actor,
        private readonly DeliveryExecution $deliveryExecution,
        private readonly CarbonInterface $actionTime,
        private readonly string $reason,
        private readonly ?string $itemId,
    ) {
    }

    public function getDeliveryExecution(): DeliveryExecution
    {
        return $this->deliveryExecution;
    }

    public function jsonSerialize(): array
    {
        return [
            'actorIdentity' => $this->actor,
            'action' => [
                'type' => self::DEFAULT_ACTION_TYPE,
                'status' => $this->deliveryExecution->getStatus(),
            ],
            'timestamp' => $this->actionTime->getTimestampMs(),
            'deliveryExecution' => [
                'id' => $this->deliveryExecution->getId(),
                'status' => $this->deliveryExecution->getStatus(),
            ],
            'resourceLink' => [
                'identifier' => $this->deliveryExecution->getLtiLaunchParameters()['resource_link_id'] ?? '',
            ],
            'itemId' => $this->itemId,
            // https://github.com/oat-sa/proctoring/blob/develop/apps/frontend/src/component/report/dropdownOptions.js#L45C13-L45C18
            'reason' => new DeliveryExecutionControlReason("[{$this->issuer->value}] $this->reason", self::SYSTEM_REASON_OTHER_CODE),
        ];
    }
}
