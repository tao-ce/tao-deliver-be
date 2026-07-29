<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Configuration;

use App\DocumentManager\Configuration\ElasticsearchConfigurationProvider;
use GuzzleHttp\ClientInterface;
use Http\Client\HttpAsyncClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ElasticsearchConfigurationProviderTest extends TestCase
{
    private ClientInterface $client;
    private HttpAsyncClient $asyncClient;
    private ?LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);
        $this->asyncClient = $this->createMock(HttpAsyncClient::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testProvideConfiguration(): void
    {
        $expected = [
            'HttpClient' => $this->client,
            'AsyncHttpClient' => $this->asyncClient,
            'Logger' => $this->logger,
            'Hosts' => ['http://localhost:9200'],
            'ApiKey' => ['apiKey', null],
            'Retries' => 2,
        ];

        $this->assertSame($expected, $this->createSubject()->provideConfiguration());
    }

    public function testProvideConfigurationWithoutLogger(): void
    {
        $this->logger = null;
        $expected = [
            'HttpClient' => $this->client,
            'AsyncHttpClient' => $this->asyncClient,
            'Hosts' => ['http://localhost:9200'],
            'ApiKey' => ['apiKey', null],
            'Retries' => 2,
        ];

        $this->assertSame($expected, $this->createSubject()->provideConfiguration());
    }

    public function testProvideQuiet(): void
    {
        $this->assertFalse($this->createSubject()->provideQuiet());
    }

    private function createSubject(): ElasticsearchConfigurationProvider
    {
        return new ElasticsearchConfigurationProvider(
            $this->client,
            $this->asyncClient,
            $this->logger,
            'http://localhost',
            '9200',
            'apiKey',
            2,
        );
    }
}
