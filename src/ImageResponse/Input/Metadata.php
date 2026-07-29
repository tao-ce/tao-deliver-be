<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\ImageResponse\Input;

final readonly class Metadata
{
    public function __construct(
        public ?string $userSessionId = null,
        public ?string $deliveryId = null,
        public ?string $sessionId = null,
        public ?string $attemptId = null,
        public ?string $itemId = null,
        public ?string $responseId = null,
        public ?int $pageNumber = null,
    ) {
    }

    public function isValid(): bool
    {
        return !in_array(null, get_object_vars($this), true);
    }
}
