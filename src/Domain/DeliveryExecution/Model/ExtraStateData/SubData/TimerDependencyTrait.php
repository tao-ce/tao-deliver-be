<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

trait TimerDependencyTrait
{
    private ?bool $hasTimer = null;

    public function hasTimer(): ?bool
    {
        return $this->hasTimer;
    }

    public function withHasTimer(bool $hasTimer): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->hasTimer = $hasTimer;

        return $deliveryExecutionExtraStateData;
    }
}
