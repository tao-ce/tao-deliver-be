<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DataStore\DTO;

class ItemsResultsDTO
{
    /** @var ItemResultDTO[][] */
    private array $itemResults = [];

    public function addItemResult(string $id, ItemResultDTO $itemResult): self
    {
        if (!empty($this->itemResults[$id])) {
            $key = array_key_last($this->itemResults[$id]);
            $this->itemResults[$id][$key]->lastAttempt = false;
        }
        $this->itemResults[$id][] = $itemResult;
        return $this;
    }

    public function getItemResults(): array
    {
        return $this->itemResults;
    }
}
