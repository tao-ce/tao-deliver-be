<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionControlReason;
use App\TestRunner\Event\Control\ControlType;
use App\TestRunner\Event\Control\DeliveryExecutionControlEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

class SecurityLogActionProcessor extends AbstractActionProcessor
{
    protected const AVAILABLE_STATUSES = [
        DeliveryExecution::STATUS_INTERACTING,
        DeliveryExecution::STATUS_SUSPENDED,
    ];

    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function getActionName(): string
    {
        return 'security-log';
    }

    public function process(DeliveryExecution $deliveryExecution, array $actionParameters): array
    {
        $parameters = $actionParameters['parameters'];

        $this->eventDispatcher->dispatch(new DeliveryExecutionControlEvent(
            $deliveryExecution,
            ControlType::from($parameters['action']),
            new DeliveryExecutionControlReason($parameters['reason']),
        ));

        return $this->getActionProcessorResponse($actionParameters, []);
    }
}
