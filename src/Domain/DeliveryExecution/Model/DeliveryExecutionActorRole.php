<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model;

use JsonSerializable;

enum DeliveryExecutionActorRole: string implements JsonSerializable
{
    case ROLE_PROCTOR = 'proctor';
    case ROLE_TEST_TAKER = 'test-taker';
    case ROLE_SECURITY_PLUGIN = 'security-plugin';
    case ROLE_SYSTEM = 'system';
    case ROLE_DELIVER_FE = 'deliver-fe';
    case ROLE_REAL_TIME_SERVICE = 'realtime';

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
