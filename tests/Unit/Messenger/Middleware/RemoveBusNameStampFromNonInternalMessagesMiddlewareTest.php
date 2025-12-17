<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Middleware;

use App\Messenger\Middleware\RemoveBusNameStampFromNonInternalMessagesMiddleware;
use OAT\Library\GraderMessengerBridge\Message\Event\DeliveryExecutionFinishedEvent;
use OAT\Library\GraderMessengerBridge\Message\Event\DeliveryPublishedEvent;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

class RemoveBusNameStampFromNonInternalMessagesMiddlewareTest extends TestCase
{
    private RemoveBusNameStampFromNonInternalMessagesMiddleware $subject;

    protected function setUp(): void
    {
        $this->subject = new RemoveBusNameStampFromNonInternalMessagesMiddleware();
    }

    public function testHandleWithNonInternalMessageType(): void
    {
        $envelope = new Envelope(
            new DeliveryExecutionFinishedEvent(
                'tenantId',
                'deliveryId',
                'deliveryExecutionId',
                'testTakerId',
                [],
                null,
                null,
                null,
            ),
            [new BusNameStamp('busName')],
        );

        $this->testHandleMessageWithNonInternalMessageType($envelope);

        $envelope = new Envelope(
            new DeliveryPublishedEvent(
                'tenantId',
                'deliveryId',
            ),
            [new BusNameStamp('busName')],
        );

        $this->testHandleMessageWithNonInternalMessageType($envelope);
    }

    private function testHandleMessageWithNonInternalMessageType(Envelope $envelope): void
    {
        $stackMock = $this->createMock(StackInterface::class);

        $middlewareMock = $this->createMock(MiddlewareInterface::class);
        $middlewareMock
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(static function (Envelope $envelope) {
                return count($envelope->all(BusNameStamp::class)) === 0;
            }), $stackMock)
            ->willReturn($envelope);


        $stackMock
            ->expects($this->once())
            ->method('next')
            ->willReturn($middlewareMock);

        $message = $this->subject->handle($envelope, $stackMock);
        $this->assertInstanceOf(Envelope::class, $message);
    }

    public function testHandleWithInternalMessageType(): void
    {
        $envelope = new Envelope(new stdClass(), [new BusNameStamp('busName')]);
        $stackMock = $this->createMock(StackInterface::class);

        $middlewareMock = $this->createMock(MiddlewareInterface::class);
        $middlewareMock
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(static function (Envelope $envelope) {
                return count($envelope->all(BusNameStamp::class)) > 0;
            }), $stackMock)
            ->willReturn($envelope);


        $stackMock
            ->expects($this->once())
            ->method('next')
            ->willReturn($middlewareMock);

        $this->assertInstanceOf(Envelope::class, $this->subject->handle($envelope, $stackMock));
    }
}
