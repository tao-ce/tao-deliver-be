<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

trait AssessmentEventsTrait
{
    private array $assessmentEvents = [];

    public function getAssessmentEvents(): array
    {
        return $this->assessmentEvents;
    }

    public function withNoAssessmentEvents(): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->assessmentEvents = [];

        return $deliveryExecutionExtraStateData;
    }

    public function withAddedAssessmentEvent(array $event): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->assessmentEvents[] = $event;

        return $deliveryExecutionExtraStateData;
    }
}
