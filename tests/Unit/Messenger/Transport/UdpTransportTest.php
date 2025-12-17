<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Transport;

use App\Client\UdpClient;
use App\Generator\UuidGenerator;
use App\Messenger\Message\InteractionMessage;
use App\Messenger\Stamp\MetadataStamp;
use App\Messenger\Transport\UdpTransport;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Serializer\SerializerInterface;

class UdpTransportTest extends TestCase
{
    /** @var UdpTransport */
    private $subject;

    /** @var UdpClient|MockObject */
    private $udpClientMock;

    /** @var UuidGenerator|MockObject */
    private $uuidGeneratorMock;

    /** @var SerializerInterface|MockObject */
    private $serializerMock;

    protected function setUp(): void
    {
        $this->udpClientMock = $this->createMock(UdpClient::class);
        $this->uuidGeneratorMock = $this->createMock(UuidGenerator::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);

        $this->subject = new UdpTransport(
            $this->udpClientMock,
            $this->uuidGeneratorMock,
            $this->serializerMock,
        );
    }

    public function testGet(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('UDP transport cannot act as receiver');

        $this->subject->get();
    }

    public function testAck(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('UDP transport cannot act as receiver');

        $this->subject->ack(new Envelope(new stdClass()));
    }

    public function testReject(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('UDP transport cannot act as receiver');

        $this->subject->reject(new Envelope(new stdClass()));
    }

    public function testSend(): void
    {
        $metadataStamp = new MetadataStamp('key');

        $this->uuidGeneratorMock
            ->expects($this->once())
            ->method('generate')
            ->willReturn('uuid');

        $message = new InteractionMessage(
            deliveryExecutionId: 'deliveryExecutionId',
            deliveryId: 'deliveryId',
            tenantId: 'tenantId',
            deliveryExecutionStartedAt: 0,
            durationInSeconds: 0,
            ipAddress: '127.0.0.1',
            position: [],
            progressPercentage: null,
            title: 'test title / section / item',
            questions: 1,
            questionsViewed: 1,
            answered: 1,
            flagged: 1,
            viewed: 1,
        );

        $envelope = new Envelope($message, [$metadataStamp]);

        $this->serializerMock
            ->expects($this->once())
            ->method('serialize')
            ->with([
                'key' => 'key',
                'payload' => $message,
            ], 'json')
            ->willReturn('serializedMessage');

        $this->udpClientMock
            ->expects($this->once())
            ->method('write')
            ->with('serializedMessage');

        $finalEnvelop = $this->subject->send($envelope);

        $this->assertInstanceOf(Envelope::class, $finalEnvelop);

        /** @var TransportMessageIdStamp $stamp */
        $stamp = $finalEnvelop->last(TransportMessageIdStamp::class);
        $this->assertInstanceOf(TransportMessageIdStamp::class, $stamp);
        $this->assertSame('uuid', $stamp->getId());
    }
}
