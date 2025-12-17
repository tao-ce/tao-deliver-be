<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\TestRunner\ActionProcessor\Handler\CantPerformActionException;

abstract class AbstractActionProcessor implements ActionProcessorInterface
{
    protected const MAX_LOG_SIZE = 3500;
    protected const AVAILABLE_STATUSES = [
        DeliveryExecution::STATUS_INTERACTING,
    ];

    /**
     * @inheritDoc
     */
    public function validateAvailability(string $deliveryExecutionStatus): void
    {
        if (!in_array($deliveryExecutionStatus, static::AVAILABLE_STATUSES, true)) {
            throw CantPerformActionException::becauseStatus($this->getActionName(), $deliveryExecutionStatus);
        }
    }

    protected function getActionProcessorResponse(array $actionParameters, array $responseParameters): array
    {
        return [
            'success' => true,
            'name' => $actionParameters['name'],
            'id' => $actionParameters['id'],
            'errorCode' => null,
            'errorMessage' => null,
            'values' => $responseParameters,
        ];
    }
}
