<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Transport;

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpTransportFactory implements TransportFactoryInterface
{
    /** @var HttpClientInterface */
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new HttpTransport($this->client, $dsn, $options, $serializer);
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === strpos($dsn, 'http://')
            || 0 === strpos($dsn, 'https://');
    }
}
