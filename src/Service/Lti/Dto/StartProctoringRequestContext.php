<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Lti\Dto;

use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;

class StartProctoringRequestContext
{
    public function __construct(
        public readonly LtiMessagePayloadInterface $ltiMessagePayload,
        public readonly DeliveryExecution $deliveryExecution,
        public readonly Delivery $delivery,
        public readonly array $launchParameters,
    ) {
    }
}
