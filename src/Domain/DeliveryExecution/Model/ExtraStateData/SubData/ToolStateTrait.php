<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

trait ToolStateTrait
{
    private array $toolStates = [];

    public function getToolStates(): array
    {
        return $this->toolStates;
    }

    public function withToolState(string $toolState): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->toolStates[] = $toolState;

        return $deliveryExecutionExtraStateData;
    }
}
