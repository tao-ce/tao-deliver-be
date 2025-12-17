<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class TypeStamp implements StampInterface
{
    public function __construct(private string $type)
    {
    }

    public function getType(): string
    {
        return $this->type;
    }
}
