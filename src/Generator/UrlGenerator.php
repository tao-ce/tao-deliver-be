<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Generator;

use App\Service\ApplicationInfoService;
use InvalidArgumentException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UrlGenerator
{
    public function __construct(
        private ApplicationInfoService $applicationInfoService,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @throws RouteNotFoundException
     */
    public function generate(string $name, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_URL): string
    {
        return match ($referenceType) {
            UrlGeneratorInterface::ABSOLUTE_URL => "{$this->applicationInfoService->getBackendUrl()}{$this->urlGenerator->generate($name, $parameters)}",
            UrlGeneratorInterface::NETWORK_PATH => preg_replace(
                '/^https?:\/\//',
                '//',
                "{$this->applicationInfoService->getBackendUrl()}{$this->urlGenerator->generate($name, $parameters)}",
            ),
            default => throw new InvalidArgumentException('Unsupported reference type'),
        };
    }
}
