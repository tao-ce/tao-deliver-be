<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Transport;

use App\Client\UdpClient;
use App\Client\UdpClientFactory;
use App\Generator\UuidGenerator;
use App\Messenger\Transport\UdpTransport;
use App\Messenger\Transport\UdpTransportFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface as MessengerSerializerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class UdpTransportFactoryTest extends TestCase
{
    /** @var UdpTransportFactory */
    private $subject;

    /** @var SerializerInterface|MockObject */
    private $serializerMock;

    /** @var UdpClientFactory|MockObject */
    private $udpClientFactoryMock;

    /** @var UuidGenerator|MockObject */
    private $uuidGeneratorMock;

    protected function setUp(): void
    {
        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->udpClientFactoryMock = $this->createMock(UdpClientFactory::class);
        $this->uuidGeneratorMock = $this->createMock(UuidGenerator::class);

        $this->subject = new UdpTransportFactory(
            $this->serializerMock,
            $this->udpClientFactoryMock,
            $this->uuidGeneratorMock,
        );
    }

    public function testCreateTransport(): void
    {
        $udpClientMock = $this->createMock(UdpClient::class);
        $messengerSerializerMock = $this->createMock(MessengerSerializerInterface::class);

        $this->udpClientFactoryMock
            ->expects($this->once())
            ->method('create')
            ->with('0.0.0.0', 8080)
            ->willReturn($udpClientMock);

        $transport = $this->subject->createTransport('udp://0.0.0.0:8080', [], $messengerSerializerMock);

        $this->assertInstanceOf(UdpTransport::class, $transport);
    }

    /**
     * @dataProvider dsnProvider
     */
    public function testSupports(bool $expected, string $dsn): void
    {
        $this->assertSame($expected, $this->subject->supports($dsn, []));
    }

    public function dsnProvider(): array
    {
        return [
            [true, 'udp://0.0.0.0:8080'],
            [false, 'http://0.0.0.0:8080'],
            [false, 'https://0.0.0.0:8080'],
            [false, '0.0.0.0:8080'],
        ];
    }
}
