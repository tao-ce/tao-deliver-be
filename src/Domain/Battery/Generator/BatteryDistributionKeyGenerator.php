<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\Battery\Generator;

use App\Domain\Battery\Model\BatteryDistribution;

class BatteryDistributionKeyGenerator
{
    public static function generateBatteryDistributionKey(string $batteryId, string $userId, ?string $attemptId): string
    {
        return rtrim(
            implode(
                BatteryDistribution::DOCUMENT_KEY_DELIMITER,
                [
                    strrev($userId),
                    $batteryId,
                    $attemptId ?? '',
                ],
            ),
            BatteryDistribution::DOCUMENT_KEY_DELIMITER,
        );
    }
}
