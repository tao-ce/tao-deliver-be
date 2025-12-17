<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;

class DataStoreDeliveryExecutionActionMessage extends AbstractDeliveryExecutionAwareMessage implements
    BatteryAwareMessageInterface
{
    public const ACTION_CREATE = 'create';
    public const ACTION_DELETE = 'delete';

    private string $tenantId;
    private string $deliveryId;
    private string $userId;
    private ?string $batteryId;

    public function __construct(
        DeliveryExecution $deliveryExecution,
        private readonly string $action,
    ) {
        parent::__construct($deliveryExecution->getId());

        [$userId] = explode(DeliveryExecution::DOCUMENT_KEY_DELIMITER, $deliveryExecution->getId(), 2);
        $userId = urldecode(strrev($userId));
        $this->deliveryId = $deliveryExecution->getDeliveryId();
        $this->tenantId = $deliveryExecution->getTenantId();
        $this->userId = $userId;
        $this->setBatteryId($deliveryExecution->getLtiLaunchParameters()['battery_id'] ?? null);
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getDeliveryId(): string
    {
        return $this->deliveryId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getBatteryId(): ?string
    {
        return $this->batteryId;
    }

    public function setBatteryId(?string $batteryId): static
    {
        $this->batteryId = $batteryId;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
