<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Environment\FeatureFlagAdapterInterface;

class ScoringEligibilityChecker
{
    private const SCORING_SUBMISSION_ENABLED = 'SCORING_SUBMISSION_ENABLED';

    public function __construct(private FeatureFlagAdapterInterface $featureFlagAdapter)
    {
    }

    public function isEligible(DeliveryExecution $deliveryExecution): bool
    {
        $scoringSubmissionEnabled = $this->featureFlagAdapter->isEnabled(
            $deliveryExecution->getTenantId(),
            self::SCORING_SUBMISSION_ENABLED,
        );
        if (!$scoringSubmissionEnabled) {
            return false;
        }

        $launchParameters = $deliveryExecution->getLtiLaunchParameters();

        return isset($launchParameters['user_id'])
            && !str_contains($launchParameters['user_id'], 'anonymous');
    }
}
