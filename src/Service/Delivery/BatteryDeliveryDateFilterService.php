<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Delivery;

use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDelivery;
use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use DateTimeZone;
use Psr\Log\LoggerInterface;

readonly class BatteryDeliveryDateFilterService extends AbstractBatteryDeliveryFilterService
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public static function getDefaultPriority(): int
    {
        return 100;
    }

    public function filter(Battery $battery, array $ltiParameters): Battery
    {
        $filteredBattery = clone $battery;
        $filteredBattery->deliveries = array_filter(
            $filteredBattery->deliveries,
            fn(BatteryDelivery $delivery): bool => $this->isValid($delivery, $ltiParameters),
        );
        return $filteredBattery;
    }

    private function isValid(BatteryDelivery $delivery, array $ltiParameters): bool
    {
        $validationResult = null;

        if (!$this->isValidationDateDefined($delivery)) {
            return true;
        }

        $isTimezoneClaimPresent = isset($ltiParameters['custom'], $ltiParameters['custom']['timezone'])
            && $this->isTimezoneClaimValid($ltiParameters['custom']['timezone']);

        if (!$isTimezoneClaimPresent) {
            $this->logger->warning('Timezone claim is missing in LTI parameters or is in invalid format.');
            return false;
        }

        if ($delivery->startDateValidation !== null) {
            $validationResult = $this->validateStartDate(
                $delivery->startDateValidation,
                $ltiParameters['custom']['timezone'],
            );
        }

        if ($validationResult !== false && $delivery->endDateValidation !== null) {
            $validationResult = $this->validateEndDate(
                $delivery->endDateValidation,
                $ltiParameters['custom']['timezone'],
            );
        }

        if ($validationResult === null) {
            return false;
        }

        return $validationResult;
    }

    private function isValidationDateDefined(BatteryDelivery $delivery): bool
    {
        return $delivery->startDateValidation !== null || $delivery->endDateValidation !== null;
    }

    private function validateStartDate(?int $startDateValidation, string $timezone): bool
    {
        $startDateValidation = $startDateValidation / 1000;
        $timezone = new CarbonTimeZone($timezone);
        $currentDate = new Carbon('now', $timezone);
        $startValidation = new Carbon("@$startDateValidation");
        $startValidationAdjusted = new Carbon($startValidation->format('Y-m-d H:i:s'), $timezone);

        return $currentDate->gte($startValidationAdjusted);
    }

    private function validateEndDate(?int $endDateValidation, string $timezone): bool
    {
        $endDateValidation = $endDateValidation / 1000;
        $timezone = new CarbonTimeZone($timezone);
        $currentDate = new Carbon('now', $timezone);
        $endValidation = new Carbon("@$endDateValidation", $timezone);
        $endDateValidationAdjusted = new Carbon($endValidation->format('Y-m-d H:i:s'), $timezone);

        return $currentDate->lte($endDateValidationAdjusted);
    }
    private function isTimezoneClaimValid(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers());
    }
}
