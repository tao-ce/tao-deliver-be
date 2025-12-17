<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Generator\Asset;

interface SignedUrlGeneratorInterface
{
    public function generateDownloadUrl(string $path, ?string $url = null, array $queryParameters = [], ?int $ttl = null): string;

    public function generateUploadUrl(?string $path = null): string;

    public function getName(): string;

    public function getFeServiceId(): string;

    public function getUploadMethod(): ?string;
}
