<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Delivery;

use App\Domain\Battery\Model\Battery;

readonly class BatteryDeliveryRandomFilterService extends AbstractBatteryDeliveryFilterService
{
    public function filter(Battery $battery, array $ltiParameters): Battery
    {
        if ($battery->mode !== Battery::MODE_RANDOM_DELIVERY || !$battery->deliveries) {
            return $battery;
        }

        $filteredBattery = clone $battery;
        $deliveryId = array_rand($battery->deliveries);
        $filteredBattery->deliveries = [$deliveryId => $battery->getDelivery($deliveryId)];
        return $filteredBattery;
    }
}
