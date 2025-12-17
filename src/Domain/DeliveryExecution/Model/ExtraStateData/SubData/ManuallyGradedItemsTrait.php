<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

use Carbon\Carbon;
use DateTimeInterface;

trait ManuallyGradedItemsTrait
{
    private array $initialManuallyGradedItems = [];
    private array $finalManuallyGradedItems = [];

    /**
     * @return array<string, DateTimeInterface>
     */
    public function getInitialManuallyGradedItems(): array
    {
        return $this->initialManuallyGradedItems;
    }

    /**
     * @return array<string, DateTimeInterface>
     */
    public function getFinalManuallyGradedItems(): array
    {
        return $this->finalManuallyGradedItems;
    }

    public function withInitialManuallyGradedItem(string $itemId, int|string|DateTimeInterface $gradedAt): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->initialManuallyGradedItems[$itemId] = new Carbon($gradedAt);

        return $deliveryExecutionExtraStateData;
    }

    public function withFinalManuallyGradedItem(string $itemId, int|string|DateTimeInterface $gradedAt): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->finalManuallyGradedItems[$itemId] = new Carbon(
            is_string($gradedAt) && !preg_match('/(?:[+-]\d.:?\d.|\D)$/', $gradedAt)
            ? "{$gradedAt}Z" // TAO Grader provisions datetime values in UTC, but without the timezone specified
            : $gradedAt,
        );

        return $deliveryExecutionExtraStateData;
    }

    public function withNoManuallyGradedItem(): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->initialManuallyGradedItems = [];
        $deliveryExecutionExtraStateData->finalManuallyGradedItems = [];

        return $deliveryExecutionExtraStateData;
    }
}
