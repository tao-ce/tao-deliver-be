<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

trait UiEventsTrait
{
    private array $uiEvents = [];

    public function getUiEvents(): array
    {
        return $this->uiEvents;
    }

    public function withNoUiEvents(): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->uiEvents = [];

        return $deliveryExecutionExtraStateData;
    }

    public function withAddedUiEvents(array $events): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->uiEvents = array_merge($deliveryExecutionExtraStateData->uiEvents, $events);

        return $deliveryExecutionExtraStateData;
    }
}
