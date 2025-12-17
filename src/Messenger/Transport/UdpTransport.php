<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Transport;

use App\Client\UdpClient;
use App\Generator\UuidGenerator;
use App\Messenger\Stamp\MetadataStamp;
use Exception;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

class UdpTransport implements TransportInterface
{
    /** @var UdpClient */
    private $udpClient;

    /** @var UuidGenerator */
    private $uuidGenerator;

    /** @var SerializerInterface */
    private $serializer;

    public function __construct(
        UdpClient $udpClient,
        UuidGenerator $uuidGenerator,
        SerializerInterface $serializer,
    ) {
        $this->udpClient = $udpClient;
        $this->uuidGenerator = $uuidGenerator;
        $this->serializer = $serializer;
    }

    public function get(): iterable
    {
        throw new TransportException('UDP transport cannot act as receiver');
    }

    public function ack(Envelope $envelope): void
    {
        throw new TransportException('UDP transport cannot act as receiver');
    }

    public function reject(Envelope $envelope): void
    {
        throw new TransportException('UDP transport cannot act as receiver');
    }

    /**
     * @throws Exception
     */
    public function send(Envelope $envelope): Envelope
    {
        /** @var MetadataStamp $metadataStamp */
        $metadataStamp = $envelope->all(MetadataStamp::class)[0];

        $this->udpClient->write(
            $this->serializer->serialize([
                'key' => $metadataStamp->getContextId(),
                'payload' => $envelope->getMessage(),
            ], 'json'),
        );

        return $envelope->with(new TransportMessageIdStamp($this->uuidGenerator->generate()));
    }
}
