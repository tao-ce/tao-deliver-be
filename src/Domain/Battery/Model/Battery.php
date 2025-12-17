<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\Battery\Model;

use JsonSerializable;
use OAT\Bundle\DocumentManagerBundle\Document\AbstractDocument;

class Battery extends AbstractDocument implements JsonSerializable
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const MODE_PREFERRED_DELIVERY = 'choose_a_delivery';
    public const MODE_RANDOM_DELIVERY = 'randomly_choose_a_delivery';
    public const MODE_ALL_IN_SEQUENCE = 'all_in_sequence';

    /** @var Array<string, BatteryDelivery> */
    public array $deliveries = [];

    /** @var Array<string, BatteryDelivery> */
    private array $allDeliveries = [];

    public function __construct(
        protected $id,
        public readonly string $tenantId,
        public readonly string $name,
        public readonly string $description = '',
        public readonly string $status = self::STATUS_ACTIVE,
        public readonly string $mode = self::MODE_RANDOM_DELIVERY,
        array $deliveries = [],
    ) {
        $this->addUpdate('tenantId');
        $this->addUpdate('name');
        $this->addUpdate('description');
        $this->addUpdate('status');
        $this->addUpdate('mode');

        $this->setDeliveries($deliveries);
    }

    public function hasDelivery(BatteryDelivery $batteryDelivery): bool
    {
        return isset($this->allDeliveries[$batteryDelivery->id]);
    }

    public function getFirstDelivery(): ?BatteryDelivery
    {
        return current($this->deliveries) ?: null;
    }

    public function getDelivery(string $id): ?BatteryDelivery
    {
        return $this->deliveries[$id] ?? null;
    }

    public function getNextDelivery(string $currentDeliveryId): ?BatteryDelivery
    {
        if ($this->mode !== self::MODE_ALL_IN_SEQUENCE) {
            return null;
        }

        $currentDelivery = $this->getDelivery($currentDeliveryId);

        if ($currentDelivery === null || $currentDelivery->order === null) {
            return null;
        }

        $nextOrderPosition = $currentDelivery->order + 1;

        foreach ($this->deliveries as $delivery) {
            if ($delivery->order !== null && $delivery->order === $nextOrderPosition) {
                return $delivery;
            }
        }

        return null;
    }

    public function getPreviousDelivery(string $currentDeliveryId): ?BatteryDelivery
    {
        if ($this->mode !== self::MODE_ALL_IN_SEQUENCE) {
            return null;
        }

        $currentDelivery = $this->getDelivery($currentDeliveryId);

        if ($currentDelivery === null || $currentDelivery->order === null) {
            return null;
        }

        $previousOrderPosition = $currentDelivery->order - 1;

        foreach ($this->deliveries as $delivery) {
            if ($delivery->order !== null && $delivery->order === $previousOrderPosition) {
                return $delivery;
            }
        }

        return null;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'mode' => $this->mode,
            'deliveries' => array_values($this->deliveries),
        ];
    }

    /**
     * @param BatteryDelivery[] $deliveries
     */
    private function setDeliveries(array $deliveries): void
    {
        usort(
            $deliveries,
            static fn(BatteryDelivery $a, BatteryDelivery $b): int => $a->order <=> $b->order,
        );

        foreach ($deliveries as $delivery) {
            $this->deliveries[$delivery->id] = $delivery;
        }

        $this->allDeliveries = $this->deliveries;
        $this->addUpdate('deliveries');
    }
}
