<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution\Contract;

use App\Domain\Battery\Model\Battery;

interface BatteryDeliveryFilterInterface
{
    public function filter(Battery $battery, array $ltiParameters): Battery;
}
