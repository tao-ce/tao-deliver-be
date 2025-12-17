<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\Event;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;

class AcsControlProcessedEvent
{
    public function __construct(
        public readonly DeliveryExecution $deliveryExecution,
        public readonly string $status,
        public readonly AcsControlInterface $acsControl,
    ) {
    }
}
