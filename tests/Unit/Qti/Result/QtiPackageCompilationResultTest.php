<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Qti\Result;

use App\Qti\Result\QtiPackageCompilationResult;
use PHPUnit\Framework\TestCase;

class QtiPackageCompilationResultTest extends TestCase
{
    /** @var QtiPackageCompilationResult */
    private $subject;

    protected function setUp(): void
    {
        $this->subject = new QtiPackageCompilationResult(
            true,
            ['reports'],
            $this->getAssessmentItemRefMappingSample(),
            'compiledCompactTestFilePath',
        );
    }

    public function testItCanReturnIfSuccessful(): void
    {
        $this->assertTrue($this->subject->isSuccessful());
    }

    public function testItCanReturnCompilationReports(): void
    {
        $this->assertEquals(['reports'], $this->subject->getCompilationReports());
    }

    public function testItCanReturnCompiledCompactTestFilePath(): void
    {
        $this->assertEquals('compiledCompactTestFilePath', $this->subject->getCompiledCompactTestFilePath());
    }

    public function testItCanReturnAssessmentItemRefMapping(): void
    {
        $this->assertEquals($this->getAssessmentItemRefMappingSample(), $this->subject->getAssessmentItemRefMapping());
    }

    private function getAssessmentItemRefMappingSample(): array
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
