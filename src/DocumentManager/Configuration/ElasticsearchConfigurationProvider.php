<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Configuration;

use GuzzleHttp\ClientInterface;
use Http\Client\HttpAsyncClient;
use OAT\Bundle\ElasticsearchDocumentManagerBundle\Configuration\ConfigurationProviderInterface;
use Psr\Log\LoggerInterface;

readonly class ElasticsearchConfigurationProvider implements ConfigurationProviderInterface
{
    public function __construct(
        private ClientInterface $client,
        private HttpAsyncClient $asyncClient,
        private ?LoggerInterface $logger,
        private string $host,
        private string $port,
        private string $apiKey,
        private int $retries,
    ) {
    }

    public function provideConfiguration(): array
    {
        return array_filter([
            'HttpClient' => $this->client,
            'AsyncHttpClient' => $this->asyncClient,
            'Logger' => $this->logger,
            'Hosts' => [sprintf('%s:%s', $this->host, $this->port)],
            'ApiKey' => [$this->apiKey, null],
            'Retries' => $this->retries,
        ]);
    }

    public function provideQuiet(): bool
    {
        return false;
    }
}
