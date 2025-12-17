<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\Delivery\Model\Statistics;

use JsonSerializable;

class DeliveryStatistics implements JsonSerializable
{
    /** @var int[] */
    private array $statistics = [];

    public function __construct(array $statistics = [])
    {
        foreach ($statistics as $statisticName => $statisticValue) {
            $this->setStatistic($statisticName, $statisticValue);
        }
    }

    /**
     * @return int[]
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }

    public function getStatistic(string $statisticName): ?int
    {
        return $this->statistics[$statisticName] ?? null;
    }

    public function setStatistic(string $statisticName, int $statisticValue): self
    {
        $this->statistics[$statisticName] = $statisticValue;

        return $this;
    }

    public function incrementStatistic(string $statisticName): self
    {
        if (null === $currentValue = $this->getStatistic($statisticName)) {
            $this->setStatistic($statisticName, 1);
        } else {
            $this->setStatistic($statisticName, $currentValue + 1);
        }

        return $this;
    }

    public function jsonSerialize(): array
    {
        return $this->getStatistics();
    }
}
