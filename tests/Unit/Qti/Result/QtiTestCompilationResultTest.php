<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Qti\Result;

use App\Qti\Result\QtiTestCompilationResult;
use PHPUnit\Framework\TestCase;

class QtiTestCompilationResultTest extends TestCase
{
    public function testGetCompactTestDocumentPath(): void
    {
        $compactTestDocumentPath = 'path';
        $subject = new QtiTestCompilationResult($compactTestDocumentPath, []);

        $this->assertEquals($compactTestDocumentPath, $subject->getCompactTestDocumentPath());
    }

    public function testGetAssessmentItemsRefMapping(): void
    {
        $compactTestDocumentPath = 'path';
        $subject = new QtiTestCompilationResult($compactTestDocumentPath, $this->getAssessmentItemsRefMappingSample());

        $this->assertEquals($this->getAssessmentItemsRefMappingSample(), $subject->getAssessmentItemsRefMapping());
    }

    private function getAssessmentItemsRefMappingSample(): array
    {
        return [
            'Item-Q01' => [
                'itemIdentifier' => 'Q01',
                'itemLabel' => 'Q01',
                'itemTitle' => 'Q01',
            ],
            'Item-Q02' => [
                'itemIdentifier' => 'Q02',
                'itemLabel' => 'Q02',
                'itemTitle' => 'Q02',
            ],
            'Item-Q03' => [
                'itemIdentifier' => 'Q03',
                'itemLabel' => 'Q03',
                'itemTitle' => 'Q03',
            ],
        ];
    }
}
