<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DataStore\DTO;

class ItemResultDTO
{
    public function __construct(
        public readonly array $responseVariable,
        public readonly array $outcomeVariable,
        public readonly ?float $startedTimeStamp,
        public readonly ?float $submittedTimeStamp,
        public readonly int $itemPosition,
        public bool $lastAttempt,
        public readonly ?int $manuallyGradedAt,
        public readonly ?array $state,
        public readonly bool $answered = false,
        public readonly array $interaction = [], // The last two are not used; keeping around for compatibility reasons
        public readonly array $customVariable = [],
    ) {
    }
}
