<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Normalizer;

use App\Domain\Battery\Model\BatteryDelivery;
use Psr\Log\LoggerInterface;

class BatteryDeliveriesNormalizer
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * @return BatteryDelivery[]
     */
    public function denormalize(array $deliveries): array
    {
        $batteryDeliveries = $keys = $duplicatedKeys = [];
        foreach ($deliveries as $delivery) {
            if (isset($keys[$delivery['id']])) {
                $duplicatedKeys[$delivery['id']] = true;
            }

            $keys[$delivery['id']] = true;
            $batteryDeliveries[] = new BatteryDelivery(
                id: $delivery['id'],
                password: $delivery['password'] ?? null,
                order: $delivery['order'] ?? null,
                startDateValidation: $delivery['startDate'] ?? null,
                endDateValidation: $delivery['endDate'] ?? null,
            );
        }
        if (!empty($duplicatedKeys)) {
            $this->logger->error(
                sprintf(
                    'Battery must contain unique deliveries. Duplicated deliveries: "%s"',
                    implode('", "', array_keys($duplicatedKeys)),
                ),
            );
        }

        return $batteryDeliveries;
    }
}
