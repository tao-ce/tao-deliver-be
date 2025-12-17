<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model;

use JsonSerializable;

enum DeliveryExecutionStatus: string implements JsonSerializable
{
    case STATUS_INITIAL = 'initial';
    case STATUS_INTERACTING = 'interacting';
    case STATUS_SUSPENDED = 'suspended';
    case STATUS_CLOSED = 'closed';
    case STATUS_TERMINATED = 'terminated';

    public function equals(string $status): bool
    {
        return $status === $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
