<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DataStore\DTO;

readonly class AssessmentDataPayloadDTO
{
    public function __construct(
        public DeliveryDTO $delivery,
        public array $ltiParameters,
        public AssessmentResultDTO $assessmentResult,
        public ?string $locale,
        public array $sessionData,
    ) {
    }
}
