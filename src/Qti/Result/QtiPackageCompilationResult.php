<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Result;

class QtiPackageCompilationResult
{
    /** @var bool */
    private $compilationSuccess;

    /** @var array */
    private $compilationReports;

    /** @var  QtiAssessmentItemRefMappingResult[] */
    private $assessmentItemRefMapping;

    /** @var string */
    private $compiledCompactTestFilePath;

    public function __construct(
        bool $compilationSuccess,
        array $compilationReports = [],
        array $assessmentItemRefMapping = [],
        ?string $compiledCompactTestFilePath = null,
    ) {
        $this->compilationSuccess = $compilationSuccess;
        $this->compilationReports = $compilationReports;
        $this->assessmentItemRefMapping = $assessmentItemRefMapping;
        $this->compiledCompactTestFilePath = $compiledCompactTestFilePath;
    }

    public function isSuccessful(): bool
    {
        return $this->compilationSuccess;
    }

    public function getCompilationReports(): array
    {
        return $this->compilationReports;
    }

    public function getAssessmentItemRefMapping(): array
    {
        return $this->assessmentItemRefMapping;
    }

    public function getCompiledCompactTestFilePath(): ?string
    {
        return $this->compiledCompactTestFilePath;
    }
}
