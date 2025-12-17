<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\Battery\Model;

use JsonSerializable;

class BatteryDelivery implements JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $password,
        public readonly ?int $order,
        public readonly ?int $startDateValidation = null,
        public readonly ?int $endDateValidation = null,
    ) {
    }

    public function matchPassword(string $password): bool
    {
        return $password === $this->password;
    }

    public function isPasswordProtected(): bool
    {
        return !empty($this->password);
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
