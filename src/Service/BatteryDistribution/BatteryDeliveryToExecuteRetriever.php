<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\BatteryDistribution;

use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDelivery;
use App\Domain\Battery\Model\BatteryDistribution;
use App\Service\DeliveryExecution\Contract\BatteryDeliveryFilterInterface;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;

class BatteryDeliveryToExecuteRetriever
{
    /**
     * @param BatteryDeliveryFilterInterface[]|iterable $filters
     */
    public function __construct(
        private readonly DeliveryExecutionServiceInterface $deliveryExecutionService,
        private readonly iterable $filters,
    ) {
    }

    /**
     * @throws DocumentNotFoundException
     */
    public function retrieve(BatteryDistribution $batteryDistribution, array $ltiLaunchParameters): BatteryDelivery
    {
        $firstDelivery = $batteryDistribution->battery->getFirstDelivery();
        if ($firstDelivery === null) {
            throw new DocumentNotFoundException(
                "No executable delivery found in battery {$batteryDistribution->battery->getId()}",
            );
        }

        foreach ($batteryDistribution->battery->deliveries as $executableDelivery) {
            $deliveryExecutionId = $this->deliveryExecutionService->createDeliveryExecutionId(
                $executableDelivery->id,
                $batteryDistribution->battery->tenantId,
                $ltiLaunchParameters,
            );
            $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecution($deliveryExecutionId);
            if (!$deliveryExecution?->isStateFinal()) {
                return $executableDelivery;
            }
        }

        return $firstDelivery;
    }

    public function filter(Battery $battery, array $ltiParameters): Battery
    {
        foreach ($this->filters as $filter) {
            $battery = $filter->filter($battery, $ltiParameters);
        }

        return $battery;
    }
}
