<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

trait RequestIpTrait
{
    private ?string $requestIp = null;

    public function getRequestIp(): ?string
    {
        return $this->requestIp;
    }

    public function withRequestIp(string $requestIp): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->requestIp = $requestIp;

        return $deliveryExecutionExtraStateData;
    }
}
