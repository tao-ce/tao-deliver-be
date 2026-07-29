<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\ImageResponse\Input;

use DateTimeInterface;

final class ImageResponse
{
    public function __construct(
        public readonly string $assetId,
        public readonly string $tenantId,
        public readonly DateTimeInterface $uploadedAt,
        public readonly string $status,
        public readonly ?Metadata $qrCodeMetadata = null,
    ) {
    }

    public ?string $userId {
        get {
            if (isset($this->userId)) {
                return $this->userId;
            }

            if (
                !$this->qrCodeMetadata?->isValid()
                || !preg_match(
                    sprintf(
                        '/^%s(.+)$/',
                        preg_quote($this->qrCodeMetadata->sessionId, '/'),
                    ),
                    $this->qrCodeMetadata->userSessionId,
                    $matches,
                )) {
                return null;
            }

            $this->userId = $matches[1];
            return $this->userId;
        }
        set {
        }
    }

    public function isValid(): bool
    {
        return $this->status === 'success'
            && $this->userId !== null;
    }
}
