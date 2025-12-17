<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\UserIdentity;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use OAT\Library\Lti1p3Core\User\UserIdentity;

class AnonymousUserIdentity extends UserIdentity
{
    public function __construct(DeliveryExecution $deliveryExecution)
    {
        parent::__construct($deliveryExecution->getOriginalUserId());
    }
}
