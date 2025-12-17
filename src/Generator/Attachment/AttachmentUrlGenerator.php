<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Generator\Attachment;

use App\Generator\Asset\CloudCdnSignedUrlGenerator;
use App\Generator\Asset\CloudStorageSignedUrlGenerator;
use App\Generator\Asset\SignedUrlGeneratorInterface;
use App\Generator\UrlGenerator;
use App\Registry\SignedUrlGeneratorRegistry;
use App\Request\Service\ContextService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class AttachmentUrlGenerator
{
    public function __construct(
        private RequestStack $requestStack,
        private SignedUrlGeneratorRegistry $signedUrlGeneratorRegistry,
        private UrlGenerator $urlGenerator,
        private ContextService $contextService,
        private string $cloudCdnUrl,
        private string $prefix,
        private bool $isFrontendUrlAbsolute,
    ) {
    }

    public function generateDownloadUrl(
        string $path,
        string $fileName = '',
        ?int $ttl = null,
        $forFrontEnd = true,
    ): string {
        return $this->getCdnSignedUrlGenerator()
            ->generateDownloadUrl(
                $path,
                $this->generateAssetDownloadUrl($path, $forFrontEnd),
                $fileName ? ['responseDisposition' => "attachment; filename=\"$fileName\""] : [],
                $ttl,
            );
    }

    public function generateUploadUrl(string $path): string
    {
        return $this->getSignedUrlGenerator()->generateUploadUrl($path);
    }

    public function getUploadMethod(): string
    {
        return $this->getSignedUrlGenerator()->getUploadMethod();
    }

    private function generateAssetDownloadUrl(string $path, bool $forFrontEnd = true): ?string
    {
        try {
            $route = 'api_v1_attachments_download_file';
            $params = compact('path');
            if ($forFrontEnd) {
                return $this->getSchemePrefix()
                    . $this->urlGenerator->generate(
                        $route,
                        $params,
                        UrlGeneratorInterface::NETWORK_PATH,
                    );
            }
            return $this->urlGenerator->generate($route, $params);
        } catch (RouteNotFoundException) {
            return sprintf('%s/%s', dirname($this->cloudCdnUrl), $this->prefix);
        }
    }

    private function getSignedUrlGenerator(): SignedUrlGeneratorInterface
    {
        return $this->signedUrlGeneratorRegistry->getGenerator(CloudStorageSignedUrlGenerator::NAME);
    }

    private function getCdnSignedUrlGenerator(): SignedUrlGeneratorInterface
    {
        return $this->contextService->fetch()->isReview()
            ? $this->signedUrlGeneratorRegistry->getGenerator(CloudCdnSignedUrlGenerator::NAME)
            : $this->getSignedUrlGenerator();
    }

    private function getSchemePrefix(): string
    {
        return $this->isFrontendUrlAbsolute && $this->requestStack->getCurrentRequest()
            ? "{$this->requestStack->getCurrentRequest()->getScheme()}:"
            : '';
    }
}
