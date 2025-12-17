<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Asset;

use League\Flysystem\FilesystemReader;
use League\Flysystem\UnableToRetrieveMetadata;
use League\MimeTypeDetection\MimeTypeDetector;

class MimeTypeDetectorService
{
    public function __construct(private readonly MimeTypeDetector $mimeTypeDetector)
    {
    }

    public function detect(FilesystemReader $storage, string $path): string
    {
        try {
            $mimeType = $storage->mimeType($path);
        } catch (UnableToRetrieveMetadata) {
            $mimeType = 'text/plan';
        }

        if (str_starts_with($mimeType, 'text')) {
            $mimeType = $this->mimeTypeDetector->detectMimeTypeFromPath($path);
        }

        return $mimeType ?? 'text/csv';
    }
}
