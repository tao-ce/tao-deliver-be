<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

trait FlaggedItemsTrait
{
    private array $flaggedItems = [];

    public function getFlaggedItems(): array
    {
        return array_keys($this->flaggedItems);
    }

    public function withFlaggedItem(string $itemIdentifier): self
    {
        if ($this->isItemFlagged($itemIdentifier)) {
            return $this;
        }

        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->flaggedItems[$itemIdentifier] = true;

        return $deliveryExecutionExtraStateData;
    }

    public function withUnFlaggedItem(string $itemIdentifier): self
    {
        if (!$this->isItemFlagged($itemIdentifier)) {
            return $this;
        }

        $deliveryExecutionExtraStateData = clone $this;
        unset($deliveryExecutionExtraStateData->flaggedItems[$itemIdentifier]);

        return $deliveryExecutionExtraStateData;
    }

    public function isItemFlagged(string $itemIdentifier): bool
    {
        return isset($this->flaggedItems[$itemIdentifier]);
    }
}
