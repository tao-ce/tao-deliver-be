<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Delivery;

use App\Domain\Delivery\Model\Delivery;
use App\Environment\FeatureFlagAdapterInterface;

class ScoringEligibilityChecker
{
    private const SCORING_SUBMISSION_ENABLED = 'SCORING_SUBMISSION_ENABLED';

    public function __construct(private FeatureFlagAdapterInterface $featureFlagAdapter)
    {
    }

    public function isEligible(Delivery $delivery): bool
    {
        return $this->featureFlagAdapter->isEnabled(
            $delivery->getTenantId(),
            self::SCORING_SUBMISSION_ENABLED,
        );
    }
}
