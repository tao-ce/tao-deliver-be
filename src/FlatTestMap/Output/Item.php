<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\FlatTestMap\Output;

use JsonSerializable;

final readonly class Item implements JsonSerializable
{
    public function __construct(public string $id, public string $title, public array $responseIds)
    {
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
