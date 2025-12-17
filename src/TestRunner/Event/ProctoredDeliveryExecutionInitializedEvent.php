<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Event;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;

class ProctoredDeliveryExecutionInitializedEvent extends AbstractDeliveryExecutionAwareEvent
{
    public function __construct(private string $triggeredBy, DeliveryExecution $deliveryExecution)
    {
        parent::__construct($deliveryExecution);
    }

    public function getTriggeredBy(): string
    {
        return $this->triggeredBy;
    }
}
