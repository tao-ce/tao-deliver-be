<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\Proctoring\AcsResumeEmulation\Event;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;

class ProctoredAssessmentForceResumed
{
    /**
     * created in scope of fixing conflict between proctoring termination based on acs action
     * and test runner force resuming based on `deliverySettings.forceResume` claim.
     * This approach should be reconsidered by deprecation possibility to apply force resume claim
     * on proctored executions
     */
    public function __construct(public readonly DeliveryExecution $deliveryExecution)
    {
    }
}
