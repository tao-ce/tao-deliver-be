<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Service\Contract;

use DateTimeInterface;

interface ArgumentItemResultInterface
{
    public function getTimestamp(): DateTimeInterface;
    /**
     * @return ArgumentOutcomeVariableInterface[]
     */
    public function getOutcomeVariableAssoc(): array;

    public function getOverallComment(): ?array;
}
