<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Workflow;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;

class DeliveryExecutionStateMachineTest extends AbstractStateMachine
{
    protected function getStateMachineIdentifier(): string
    {
        return 'delivery_execution';
    }

    protected function getExpectedPlaces(): array
    {
        return [
            DeliveryExecution::STATUS_INITIAL,
            DeliveryExecution::STATUS_INTERACTING,
            DeliveryExecution::STATUS_SUSPENDED,
            DeliveryExecution::STATUS_TERMINATED,
            DeliveryExecution::STATUS_CLOSED,
        ];
    }

    protected function getExpectedTransitions(): array
    {
        return [
            'begin_attempt' => [
                'froms' => [DeliveryExecution::STATUS_INITIAL],
                'tos' => [DeliveryExecution::STATUS_INTERACTING],
            ],
            'close_attempt' => [
                'froms' => [DeliveryExecution::STATUS_INTERACTING],
                'tos' => [DeliveryExecution::STATUS_CLOSED],
            ],
            'suspend_attempt' => [
                'froms' => [DeliveryExecution::STATUS_INTERACTING],
                'tos' => [DeliveryExecution::STATUS_SUSPENDED],
            ],
            'reinitialize_suspended_attempt' => [
                'froms' => [DeliveryExecution::STATUS_SUSPENDED],
                'tos' => [DeliveryExecution::STATUS_INTERACTING],
            ],
            'terminate_attempt' => [
                'froms' => [DeliveryExecution::STATUS_INTERACTING],
                'tos' => [DeliveryExecution::STATUS_TERMINATED],
            ],
            'terminate_suspended_attempt' => [
                'froms' => [DeliveryExecution::STATUS_SUSPENDED],
                'tos' => [DeliveryExecution::STATUS_TERMINATED],
            ],
        ];
    }
}
