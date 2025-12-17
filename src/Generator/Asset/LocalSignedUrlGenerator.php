<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Generator\Asset;

use App\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LocalSignedUrlGenerator implements SignedUrlGeneratorInterface
{
    public const NAME = 'local';

    public const FE_SERVICE_ID = 'base64';

    public function __construct(private UrlGenerator $urlGenerator)
    {
    }

    public function generateDownloadUrl(
        string $path,
        ?string $url = null,
        array $queryParameters = [],
        ?int $ttl = null,
    ): string {
        if (null === $url) {
            $url = $this->getDefaultUrl();
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query([
            'path' => $path,
        ]);
    }

    public function generateUploadUrl(?string $path = null): string
    {
        return $this->urlGenerator->generate(
            'api_v1_attachments_upload_file',
            compact('path'),
            UrlGeneratorInterface::NETWORK_PATH,
        );
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getFeServiceId(): string
    {
        return self::FE_SERVICE_ID;
    }

    public function getUploadMethod(): ?string
    {
        return 'PUT';
    }

    private function getDefaultUrl(): string
    {
        return $this->urlGenerator->generate('api_v1_get_asset', referenceType: UrlGeneratorInterface::NETWORK_PATH);
    }
}
