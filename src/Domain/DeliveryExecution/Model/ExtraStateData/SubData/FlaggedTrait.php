<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

trait FlaggedTrait
{
    private bool $isFlagged = false;

    public function isFlagged(): bool
    {
        return $this->isFlagged;
    }

    public function withIsFlagged(bool $isFlagged): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->isFlagged = $isFlagged;

        return $deliveryExecutionExtraStateData;
    }
}
