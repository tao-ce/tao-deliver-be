<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Service\Battery;

use App\Domain\Battery\Model\BatteryDistribution;
use App\Repository\BatteryDistributionRepository;
use App\Repository\DeliveryRepository;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use App\TestRunner\ActionProcessor\Exception\ConflictException;

readonly class AssignLocaleToBatteryDistributionService
{
    public function __construct(
        private DeliveryExecutionService $deliveryExecutionService,
        private DeliveryRepository $deliveryRepository,
        private BatteryDistributionRepository $batteryDistributionRepository,
        private BatteryService $batteryService,
    ) {
    }

    public function assign(string $batteryDistributionId, string $locale): void
    {
        $batteryDistribution = $this->batteryDistributionRepository->find($batteryDistributionId);

        if ($batteryDistribution->getLocale() !== null) {
            throw new ConflictException('Locale has already been set and cannot be overridden.');
        }

        $battery = $batteryDistribution->battery;
        $supportedLocales = $this->batteryService->getCommonLocales($battery);

        if (!in_array($locale, $supportedLocales, true)) {
            throw new ConflictException('Selected locale is not supported by the battery.');
        }

        $batteryDistribution->setLocale($locale);
        $this->batteryDistributionRepository->save($batteryDistribution);
        $this->assignLocaleToChildDeliveryExecutions($batteryDistribution, $locale);
    }

    private function assignLocaleToChildDeliveryExecutions(BatteryDistribution $batteryDistribution, string $locale): void
    {
        foreach ($batteryDistribution->battery->deliveries as $delivery) {
            $deliveryExecutionId = $this->deliveryExecutionService->createDeliveryExecutionId(
                $delivery->id,
                $batteryDistribution->battery->tenantId,
                [
                    'user_id' => $batteryDistribution->userId,
                ],
            );

            $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecution($deliveryExecutionId);

            if (!$deliveryExecution) {
                continue;
            }

            $this->deliveryExecutionService->setLocaleForDeliveryExecution(
                $this->deliveryRepository->find($deliveryExecution->getDeliveryId()),
                $deliveryExecution,
                $locale,
            );
        }
    }
}
