<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Result;

class QtiTestCompilationResult
{
    /** @var string */
    private $compactTestDocumentPath;

    /** @var QtiAssessmentItemRefMappingResult[] */
    private $assessmentItemRefMapping;

    public function __construct(string $compactTestDocumentPath, array $assessmentItemRefMapping)
    {
        $this->compactTestDocumentPath = $compactTestDocumentPath;
        $this->assessmentItemRefMapping = $assessmentItemRefMapping;
    }

    public function getCompactTestDocumentPath(): string
    {
        return $this->compactTestDocumentPath;
    }

    public function getAssessmentItemsRefMapping(): array
    {
        return $this->assessmentItemRefMapping;
    }
}
