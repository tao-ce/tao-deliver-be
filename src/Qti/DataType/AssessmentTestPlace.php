<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\DataType;

use App\TestRunner\Service\TestSessionNavigator;
use qtism\runtime\tests\AssessmentTestPlace as QtiAssessmentTestPlace;

class AssessmentTestPlace extends QtiAssessmentTestPlace
{
    /**
     * @param string $name
     * @return bool|int
     */
    public static function getConstantByName($name): bool|int
    {
        return match ($name) {
            TestSessionNavigator::SCOPE_TEST_PART => self::TEST_PART,
            TestSessionNavigator::SCOPE_SECTION, TestSessionNavigator::LEGACY_SCOPE_SECTION => self::ASSESSMENT_SECTION,
            TestSessionNavigator::SCOPE_ITEM, TestSessionNavigator::LEGACY_SCOPE_ITEM => self::ASSESSMENT_ITEM,
            TestSessionNavigator::SCOPE_TEST, TestSessionNavigator::LEGACY_SCOPE_TEST => self::ASSESSMENT_TEST,
            default => false,
        };
    }
}
