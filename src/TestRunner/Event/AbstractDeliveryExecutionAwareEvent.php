<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Event;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractDeliveryExecutionAwareEvent extends Event
{
    private DeliveryExecution $deliveryExecution;

    public function __construct(DeliveryExecution $deliveryExecution)
    {
        $this->deliveryExecution = $deliveryExecution;
    }

    public function getDeliveryExecution(): DeliveryExecution
    {
        return $this->deliveryExecution;
    }
}
