<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

use DateTimeInterface;
use JetBrains\PhpStorm\Immutable;

#[Immutable(allowedWriteScope: Immutable::PRIVATE_WRITE_SCOPE)]
trait InitialStartTimestampTrait
{
    private int $initialStartTimestamp;

    public function getInitialStartTimestamp(): int
    {
        return $this->initialStartTimestamp ?? 0;
    }

    public function withInitialStartTimestamp(int $initialStartTimestamp): self
    {
        if (isset($this->initialStartTimestamp)) {
            return $this;
        }

        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->initialStartTimestamp = $initialStartTimestamp;

        return $deliveryExecutionExtraStateData;
    }
}
