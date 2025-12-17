<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DynamicQueryApi\Model;

class SearchResponse
{
    public function __construct(
        private readonly array $data,
        private readonly int $totalResults,
        private readonly array $lastId,
    ) {
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getTotalResults(): int
    {
        return $this->totalResults;
    }

    public function getLastId(): array
    {
        return $this->lastId;
    }
}
