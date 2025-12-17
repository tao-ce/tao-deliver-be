<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\ImageResponse\Input;

use DateTimeInterface;

final readonly class ImageResponse
{
    public function __construct(
        public string $assetId,
        public string $tenantId,
        public DateTimeInterface $uploadedAt,
        public string $userId,
        public string $status,
        public ?Metadata $qrCodeMetadata = null,
    ) {
    }

    public function isValid(): bool
    {
        return $this->status === 'success'
            && $this->qrCodeMetadata?->isValid();
    }
}
