<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Twig;

use App\Generator\Asset\CloudCdnSignedUrlGenerator;
use App\Registry\SignedUrlGeneratorRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SignedUrlExtension extends AbstractExtension
{
    /** @var SignedUrlGeneratorRegistry */
    private $signedUrlGeneratorRegistry;

    public function __construct(SignedUrlGeneratorRegistry $signedUrlGeneratorRegistry)
    {
        $this->signedUrlGeneratorRegistry = $signedUrlGeneratorRegistry;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('signAssetUrl', [$this, 'signAssetUrl']),
        ];
    }

    public function signAssetUrl(string $assetPath): string
    {
        return $this->signedUrlGeneratorRegistry
            ->getGenerator(CloudCdnSignedUrlGenerator::NAME)
            ->generateDownloadUrl($assetPath);
    }
}
