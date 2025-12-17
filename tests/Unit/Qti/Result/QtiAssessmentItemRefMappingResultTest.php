<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Qti\Result;

use App\Qti\Result\QtiAssessmentItemRefMappingResult;
use PHPUnit\Framework\TestCase;

class QtiAssessmentItemRefMappingResultTest extends TestCase
{
    public function testGetIdentifier(): void
    {
        $identifier = 'identifier';
        $subject = new QtiAssessmentItemRefMappingResult($identifier);

        $this->assertEquals($identifier, $subject->getIdentifier());
    }

    /**
     * @dataProvider getLabelDataProvider
     */
    public function testGetLabel(QtiAssessmentItemRefMappingResult $subject, ?string $expectedLabel): void
    {
        $this->assertEquals($expectedLabel, $subject->getLabel());
    }

    /**
     * @dataProvider getTitleDataProvider
     */
    public function testGetTitle(QtiAssessmentItemRefMappingResult $subject, ?string $expectedTitle): void
    {
        $this->assertEquals($expectedTitle, $subject->getTitle());
    }

    public function testNormalize(): void
    {
        $identifier = 'identifier';
        $label = 'label';
        $title = 'title';
        $subject = new QtiAssessmentItemRefMappingResult($identifier, $label, $title);

        $expectedNormalizationOutput = [
            'itemIdentifier' => $identifier,
            'itemLabel' => $label,
            'itemTitle' => $title,
        ];

        $this->assertEquals($expectedNormalizationOutput, $subject->normalize());
    }

    public function getLabelDataProvider(): array
    {
        return [
            'label not provided' => [
                new QtiAssessmentItemRefMappingResult('identifier', null),
                null,
            ],
            'label provided' => [
                new QtiAssessmentItemRefMappingResult('identifier', 'label'),
                'label',
            ],
        ];
    }

    public function getTitleDataProvider(): array
    {
        return [
            'title not provided' => [
                new QtiAssessmentItemRefMappingResult('identifier', null, null),
                null,
            ],
            'title provided' => [
                new QtiAssessmentItemRefMappingResult('identifier', null, 'title'),
                'title',
            ],
        ];
    }
}
