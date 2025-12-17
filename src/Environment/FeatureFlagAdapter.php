<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Environment;

use OAT\Library\EnvironmentManagementClient\Exception\FeatureFlagNotFoundException;
use OAT\Library\EnvironmentManagementClient\Repository\FeatureFlagRepositoryInterface;

class FeatureFlagAdapter implements FeatureFlagAdapterInterface
{
    public function __construct(
        private readonly FeatureFlagRepositoryInterface $featureFlagRepository,
    ) {
    }

    public function isEnabled(string $tenantId, string $flag, bool $default = false): bool
    {
        try {
            $featureFlag = $this->featureFlagRepository->find($tenantId, $flag);

            return filter_var($featureFlag->getValue(), FILTER_VALIDATE_BOOLEAN);
        } catch (FeatureFlagNotFoundException) {
            return $default;
        }
    }
}
