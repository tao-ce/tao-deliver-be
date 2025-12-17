<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

trait TraceDataTrait
{
    private array $traceData = [];

    public function getTraceData(): array
    {
        return $this->traceData;
    }

    public function withTraceData(array $traceData): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->traceData[] = $traceData;

        return $deliveryExecutionExtraStateData;
    }
}
