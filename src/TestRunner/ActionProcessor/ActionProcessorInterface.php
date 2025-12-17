<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;

interface ActionProcessorInterface
{
    public function getActionName(): string;

    public function process(DeliveryExecution $deliveryExecution, array $actionParameters): array;

    /**
     * @throw CantPerformActionException
     */
    public function validateAvailability(string $deliveryExecutionStatus): void;
}
