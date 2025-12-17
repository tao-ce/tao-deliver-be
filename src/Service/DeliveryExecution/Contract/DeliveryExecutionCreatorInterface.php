<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution\Contract;

use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;

interface DeliveryExecutionCreatorInterface
{
    public function getDeliveryExecution(
        Delivery $delivery,
        array $parameters,
        ?string $locale = null,
    ): DeliveryExecution;

    public function createDeliveryExecutionId(string $deliveryId, string $tenantId, array $parameters): string;

    public function createDeliveryExecution(
        Delivery $delivery,
        string $deliveryExecutionId,
        array $parameters,
        ?string $qtiCompactTestFilePath = null,
        ?DeliveryExecutionExtraStateData $extraStateData = null,
        ?string $locale = null,
    ): DeliveryExecution;

    public function createDeliveryExecutionFromSeed(
        Delivery $delivery,
        DeliveryExecution $deliveryExecution,
        array $parameters,
        ?string $locale = null,
    ): DeliveryExecution;
}
