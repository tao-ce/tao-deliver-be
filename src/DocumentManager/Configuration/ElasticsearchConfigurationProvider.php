<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Configuration;

use OAT\Bundle\ElasticsearchDocumentManagerBundle\Configuration\ConfigurationProviderInterface;

readonly class ElasticsearchConfigurationProvider implements ConfigurationProviderInterface
{
    public function __construct(
        private string $host,
        private string $port,
        private string $apiKey,
    ) {
    }

    public function provideConfiguration(): array
    {
        return [
            'hosts' => [sprintf('%s:%s', $this->host, $this->port)],
            'apiKey' => [$this->apiKey, null],
        ];
    }

    public function provideQuiet(): bool
    {
        return false;
    }
}
