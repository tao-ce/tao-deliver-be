<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service;

use LogicException;
use Symfony\Component\HttpFoundation\RequestStack;

class ApplicationInfoService
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $backendUrl,
        private readonly int $cookieDomainLevelMax,
    ) {
    }

    /**
     * @throws LogicException
     */
    public function getBackendUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (empty($this->backendUrl) && $request === null) {
            throw new LogicException('Backend URL cannot be determined in this context');
        }

        return $this->backendUrl ?: $request->getSchemeAndHttpHost();
    }

    public function getCookieDomain(): string
    {
        $host = parse_url($this->getBackendUrl(), PHP_URL_HOST);

        return implode(
            '.',
            array_slice(explode('.', $host), -$this->cookieDomainLevelMax),
        );
    }
}
