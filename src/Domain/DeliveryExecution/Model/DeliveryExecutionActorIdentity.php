<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model;

use JsonSerializable;

class DeliveryExecutionActorIdentity implements JsonSerializable
{
    private readonly string $id;

    public function __construct(
        ?string $id,
        private readonly string $name,
        private readonly DeliveryExecutionActorRole $role,
        private readonly ?string $userAgent,
        private readonly ?string $ip,
    ) {
        $this->id = $id ?? 'anonymous';
    }

    public function getRole(): DeliveryExecutionActorRole
    {
        return $this->role;
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
