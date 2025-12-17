<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Event\Control;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorRole;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionControlReason;
use App\TestRunner\Event\AbstractDeliveryExecutionAwareEvent;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class DeliveryExecutionControlEvent extends AbstractDeliveryExecutionAwareEvent
{
    public function __construct(
        DeliveryExecution $deliveryExecution,
        public readonly ControlType $controlType,
        public readonly ?DeliveryExecutionControlReason $controlReason = null,
        public readonly DeliveryExecutionActorRole $deliveryExecutionActorRole = DeliveryExecutionActorRole::ROLE_TEST_TAKER,
        public readonly ControlStatus $controlStatus = ControlStatus::SUCCESS,
        public readonly CarbonInterface $timestamp = new Carbon(),
    ) {
        parent::__construct($deliveryExecution);
    }
}
