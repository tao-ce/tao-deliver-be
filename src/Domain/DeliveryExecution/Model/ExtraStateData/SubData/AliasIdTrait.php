<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

trait AliasIdTrait
{
    private ?string $aliasId = null;

    public function getAliasId(): ?string
    {
        return $this->aliasId;
    }

    public function withAliasId(string $aliasId): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->aliasId = $aliasId;

        return $deliveryExecutionExtraStateData;
    }
}
