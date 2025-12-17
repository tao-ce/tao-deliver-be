<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ApplicationInfoService;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ApplicationInfoServiceTest extends TestCase
{
    private const COOKIE_DOMAIN_LEVEL_MAX = 3;

    /**
     * @dataProvider providerTestGetBackendUrl
     */
    public function testGetBackendUrl(string $expected, string $backendUrl): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $subject = new ApplicationInfoService($requestStack, $backendUrl, self::COOKIE_DOMAIN_LEVEL_MAX);

        self::assertEquals($expected, $subject->getBackendUrl());
    }

    public function providerTestGetBackendUrl(): array
    {
        return [
            'Environment variable + request' => [getenv('DELIVER_BACKEND_URL'), getenv('DELIVER_BACKEND_URL')],
            'Request only' => [(new Request())->getSchemeAndHttpHost(), ''],
        ];
    }

    public function testExceptionWhenNoBackendUrl(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Backend URL cannot be determined in this context');

        $requestStack = new RequestStack();
        $subject = new ApplicationInfoService($requestStack, '', self::COOKIE_DOMAIN_LEVEL_MAX);

        $subject->getBackendUrl();
    }

    /**
     * @dataProvider provideForGetCookieDomain
     */
    public function testGetCookieDomain(string $expected, string $backendUrl): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $subject = new ApplicationInfoService($requestStack, $backendUrl, self::COOKIE_DOMAIN_LEVEL_MAX);

        self::assertEquals($expected, $subject->getCookieDomain());
    }

    public function provideForGetCookieDomain(): array
    {
        return [
            'Short domain' => ['example.com', 'https://example.com'],
            'Full domain' => ['namespace.example.com', 'https://namespace.example.com'],
            'Long domain' => ['namespace.example.com', 'https://www.namespace.example.com'],
        ];
    }
}
