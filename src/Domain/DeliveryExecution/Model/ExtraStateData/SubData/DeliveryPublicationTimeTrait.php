<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

use DateTimeInterface;

trait DeliveryPublicationTimeTrait
{
    private ?DateTimeInterface $deliveryPublicationTime = null;

    public function getDeliveryPublicationTime(): ?DateTimeInterface
    {
        return $this->deliveryPublicationTime;
    }

    public function withDeliveryPublicationTime(DateTimeInterface $deliveryPublicationTime): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->deliveryPublicationTime = $deliveryPublicationTime;

        return $deliveryExecutionExtraStateData;
    }
}
