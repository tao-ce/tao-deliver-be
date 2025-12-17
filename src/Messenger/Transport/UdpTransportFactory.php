<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Transport;

use App\Client\UdpClientFactory;
use App\Generator\UuidGenerator;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface as MessengerSerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

class UdpTransportFactory implements TransportFactoryInterface
{
    /** @var SerializerInterface */
    private $serializer;

    /** @var UdpClientFactory */
    private $udpClientFactory;

    /** @var UuidGenerator */
    private $uuidGenerator;

    public function __construct(
        SerializerInterface $serializer,
        UdpClientFactory $udpClientFactory,
        UuidGenerator $uuidGenerator,
    ) {
        $this->serializer = $serializer;
        $this->udpClientFactory = $udpClientFactory;
        $this->uuidGenerator = $uuidGenerator;
    }

    public function createTransport(
        string $dsn,
        array $options,
        MessengerSerializerInterface $serializer,
    ): TransportInterface {
        return new UdpTransport(
            $this->udpClientFactory->create(
                parse_url($dsn, PHP_URL_HOST),
                parse_url($dsn, PHP_URL_PORT),
            ),
            $this->uuidGenerator,
            $this->serializer,
        );
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === strpos($dsn, 'udp://');
    }
}
