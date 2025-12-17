<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

trait AttemptTrait
{
    private int $attempt = 0;

    public function getAttempt(): int
    {
        return $this->attempt;
    }

    public function withAttempt(int $attempt): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->attempt = $attempt;

        return $deliveryExecutionExtraStateData;
    }

    public function withIncrementedAttempt(): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->attempt++;

        return $deliveryExecutionExtraStateData;
    }
}
