<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Transport;

use App\Messenger\Stamp\MetadataStamp;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpTransport implements TransportInterface
{
    /** @var HttpClientInterface */
    private $client;

    /** @var string */
    private $dsn;

    /** @var array */
    private $options;

    /** @var SerializerInterface */
    private $serializer;

    public function __construct(
        HttpClientInterface $client,
        string $dsn,
        array $options,
        SerializerInterface $serializer,
    ) {
        $this->client = $client;
        $this->dsn = $dsn;
        $this->options = $options;
        $this->serializer = $serializer;
    }

    public function get(): iterable
    {
        return [];
    }

    public function ack(Envelope $envelope): void
    {
        throw new TransportException('HTTP transport cannot act as receiver');
    }

    public function reject(Envelope $envelope): void
    {
        throw new TransportException('HTTP transport cannot act as receiver');
    }

    public function send(Envelope $envelope): Envelope
    {
        $uuid = Uuid::uuid4()->toString();
        $encodedMessage = $this->serializer->encode($envelope);

        /** @var MetadataStamp $metadataStamp */
        $metadataStamp = $envelope->all(MetadataStamp::class)[0];

        $response = $this->client->request(Request::METHOD_POST, $this->dsn, [
            'headers' => [
                'Transport-Message-Id' => $uuid,
            ],
            'json' => [
                'key' => $metadataStamp->getContextId(),
                'payload' => json_decode($encodedMessage['body'], true),
            ],
        ]);

        if (!in_array($response->getStatusCode(), [200, 201, 202, 204])) {
            throw new TransportException('Failed to send message with HTTP transport');
        }

        return $envelope->with(new TransportMessageIdStamp($uuid));
    }
}
