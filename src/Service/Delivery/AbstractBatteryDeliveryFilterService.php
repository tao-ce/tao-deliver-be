<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Delivery;

use App\Service\DeliveryExecution\Contract\BatteryDeliveryFilterInterface;

abstract readonly class AbstractBatteryDeliveryFilterService implements BatteryDeliveryFilterInterface
{
    public static function getDefaultPriority(): int
    {
        return 0;
    }
}
