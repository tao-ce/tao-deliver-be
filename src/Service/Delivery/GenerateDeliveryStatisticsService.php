<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Delivery;

use App\Domain\Delivery\Model\Delivery;
use App\Domain\Delivery\Model\Statistics\DeliveryStatistics;
use App\Repository\DeliveryExecutionRepository;

class GenerateDeliveryStatisticsService
{
    /** @var DeliveryExecutionRepository */
    private $repository;

    public function __construct(DeliveryExecutionRepository $repository)
    {
        $this->repository = $repository;
    }

    public function generate(Delivery $delivery): DeliveryStatistics
    {
        $statistics = new DeliveryStatistics(['totalDeliveryExecutions' => 0]);

        foreach ($this->repository->findByDeliveryId($delivery->getId()) as $deliveryExecution) {
            $statistics->incrementStatistic('totalDeliveryExecutions');
            $statistics->incrementStatistic('deliveryExecutionsStatus' . ucfirst($deliveryExecution->getStatus()));
        }

        return $statistics;
    }
}
