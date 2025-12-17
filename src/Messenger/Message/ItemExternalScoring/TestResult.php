<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message\ItemExternalScoring;

class TestResult
{
    /** @var OutcomeVariable[] */
    public readonly array $outcomeVariableList;

    public static function fromArray(array $input): self
    {
        $testResult = new self();

        $testResult->outcomeVariableList = array_map(
            [OutcomeVariable::class, 'fromArray'],
            $input['outcomeVariable'] ?? [],
        );

        return $testResult;
    }
}
