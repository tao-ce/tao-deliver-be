<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution;

use App\Qti\Exception\ResultNotFoundException;
use App\Qti\Service\AssessmentResultService;

class DeliveryExecutionResultManagerService
{
    public function __construct(private readonly AssessmentResultService $assessmentResultService)
    {
    }

    public function dropResults(string $deliveryExecutionId): void
    {
        try {
            $this->assessmentResultService->delete($deliveryExecutionId);
        } catch (ResultNotFoundException) {
        }
    }
}
