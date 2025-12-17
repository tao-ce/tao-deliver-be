<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Delivery;

use App\Domain\Battery\Model\Battery;
use App\Lti\LtiCustomSettings;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class BatteryDeliveryPreferencesFilterService extends AbstractBatteryDeliveryFilterService
{
    public function __construct(private LtiCustomSettings $ltiCustomSettings)
    {
    }

    public function filter(Battery $battery, array $ltiParameters): Battery
    {
        if ($battery->mode !== Battery::MODE_PREFERRED_DELIVERY) {
            return $battery;
        }

        $specifiedDeliveryId = $this->ltiCustomSettings->getBatteryDeliveryId($ltiParameters);
        if ($specifiedDeliveryId === null) {
            throw new BadRequestHttpException(
                sprintf(
                    'Battery delivery ID is not specified, %s claim expected.',
                    LTICustomSettings::PARAM_BATTERY_DELIVERY_ID,
                ),
            );
        }

        $filteredBattery = clone $battery;
        foreach ($filteredBattery->deliveries as $delivery) {
            if ($delivery->id === $specifiedDeliveryId) {
                $filteredBattery->deliveries = [$delivery];
                return $filteredBattery;
            }
        }

        throw new BadRequestHttpException("Delivery with id $specifiedDeliveryId not found in the battery.");
    }
}
