<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Generator;

use Exception;
use Ramsey\Uuid\Uuid;

class UuidGenerator
{
    /**
     * @throws Exception
     */
    public function generate(): string
    {
        return Uuid::uuid4()->toString();
    }

    public function generateMedium(): string
    {
        return bin2hex(random_bytes(6));
    }

    public function generateShort(): string
    {
        return bin2hex(random_bytes(4));
    }
}
