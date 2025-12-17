<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Generator;

use qtism\common\datatypes\QtiBoolean;
use qtism\runtime\tests\AssessmentTestSession;

class ScoresExtractor
{
    private const CUT_SCORE_KEY = 'isPassed';

    public function __construct(private AssessmentTestSession $testSession)
    {
    }

    public function extractScoreOutcomes(): array
    {
        $scores = [];

        $scores[self::CUT_SCORE_KEY] = $this->extractCutScore();

        return $scores;
    }

    private function extractCutScore(): ?bool
    {
        $variable = $this->testSession->getVariable('PASS_ALL');
        $value = $variable?->getValue();
        if (!$value instanceof QtiBoolean) {
            return null;
        }
        return $value->getValue();
    }
}
