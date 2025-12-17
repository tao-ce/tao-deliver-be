<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

trait ItemStateTrait
{
    private array $itemStates = [];
    private array $temporaryItemStates = [];

    public function hasItemStates(): bool
    {
        return !empty($this->itemStates) || !empty($this->temporaryItemStates);
    }

    public function getItemStates(): array
    {
        return $this->itemStates;
    }

    public function getTemporaryItemStates(): array
    {
        return $this->temporaryItemStates;
    }

    public function getItemState(string $itemIdentifier): ?string
    {
        return $this->itemStates[$itemIdentifier] ?? null;
    }

    public function getTemporaryItemState(string $itemIdentifier): ?string
    {
        return $this->temporaryItemStates[$itemIdentifier] ?? $this->itemStates[$itemIdentifier] ?? null;
    }

    public function withItemState(string $itemIdentifier, string $itemState): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->itemStates[$itemIdentifier] = $itemState;

        return $deliveryExecutionExtraStateData;
    }

    public function withNoItemState(): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->itemStates = [];

        return $deliveryExecutionExtraStateData;
    }

    public function withTemporaryItemState(string $itemIdentifier, ?string $itemState = null): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        if (null === $itemState) {
            unset($deliveryExecutionExtraStateData->temporaryItemStates[$itemIdentifier]);
        } else {
            $deliveryExecutionExtraStateData->temporaryItemStates[$itemIdentifier] = $itemState;
        }

        return $deliveryExecutionExtraStateData;
    }

    public function withNoTemporaryItemState(): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->temporaryItemStates = [];

        return $deliveryExecutionExtraStateData;
    }
}
