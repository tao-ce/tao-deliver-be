<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DataStore\DTO;

readonly class AssessmentResultDTO
{
    public function __construct(
        public TestResultDTO $testResult,
        public ?TestResultDTO $previousTestResult,
        public SessionDTO $session,
        private ItemsResultsDTO $itemResults,
        private ?ItemsResultsDTO $previousItemResult,
    ) {
    }

    public function getItemResults(): array
    {
        return $this->itemResults->getItemResults();
    }

    public function getPreviousItemResults(): ?array
    {
        return $this->previousItemResult?->getItemResults();
    }
}
