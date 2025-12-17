<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\FlatTestMap\Output;

use JsonSerializable;

final class FlatMap
{
    /** @var Item[] */
    private array $items = [];

    public function getItems(): array
    {
        return $this->items;
    }

    public function withItem(Item $item): self
    {
        $that = clone $this;
        $that->items[] = $item;
        return $that;
    }
}
