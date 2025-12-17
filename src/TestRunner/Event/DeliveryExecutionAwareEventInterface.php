<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\TestRunner\Event;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;

interface DeliveryExecutionAwareEventInterface
{
    public function getDeliveryExecution(): DeliveryExecution;
}
