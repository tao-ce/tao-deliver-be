<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

// TODO remove the separate storage and use @see ManuallyGradedItemsTrait
trait ExternalScoredTrait
{
    private array $externalScoredItems = [];

    public function isItemScoredExternally(string $itemIdentifier): bool
    {
        return isset($this->externalScoredItems[$itemIdentifier]);
    }

    public function toArrayExternalScoredItems(): array
    {
        return $this->externalScoredItems;
    }

    public function withExternalScoredItem(string $itemIdentifier): self
    {
        if ($this->isItemScoredExternally($itemIdentifier)) {
            return $this;
        }

        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->externalScoredItems[$itemIdentifier] = true;

        return $deliveryExecutionExtraStateData;
    }

    public function resetExternalScoredItems(): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->externalScoredItems = [];

        return $deliveryExecutionExtraStateData;
    }
}
