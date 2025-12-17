<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Command\Worker;

use App\Command\Worker\ResultExtractionWorkerCommand;
use App\Messenger\Handler\ResultExtractionMessageHandler;
use App\Messenger\Message\DeliveryExecutionClosureMessage;
use App\Messenger\Message\ResultExtractionMessage;
use App\Tests\Traits\DomainTestingTrait;
use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class ResultExtractionWorkerCommandTest extends KernelTestCase
{
    use DomainTestingTrait;

    private ResultExtractionMessageHandler|MockObject $resultExtractionMessageHandlerMock;
    private SerializerInterface|MockObject $serializerMock;
    private EventDispatcherInterface|MockObject $eventDispatcherMock;

    private ResultExtractionWorkerCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        static::bootKernel();

        $this->resultExtractionMessageHandlerMock = $this->getMockBuilder(ResultExtractionMessageHandler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->command = new ResultExtractionWorkerCommand(
            $this->resultExtractionMessageHandlerMock,
            $this->serializerMock,
            $this->eventDispatcherMock,
        );
    }

    public function testItCanExtractResults(): void
    {
        $this->resultExtractionMessageHandlerMock->expects(self::once())->method('__invoke');

        $envelop = new Envelope(
            new ResultExtractionMessage(
                'payloadId',
                'e747ee141e8e-suomynona#ac0ad1ee6ab5#0a92fab3230134cca6eadd9898325b9b2ae67998#6',
            ),
        );
        $this->serializerMock
            ->expects(self::once())
            ->method('decode')
            ->willReturn($envelop);

        $this->eventDispatcherMock
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [new WorkerMessageReceivedEvent($envelop, ResultExtractionWorkerCommand::NAME)],
                [new WorkerMessageHandledEvent($envelop, ResultExtractionWorkerCommand::NAME)],
            );

        (new CommandTester($this->command))
            ->setInputs(
                ['{"body":"{\"id\":\"payloadId\",\"deliveryExecutionId\":\"e747ee141e8e-suomynona#ac0ad1ee6ab5#0a92fab3230134cca6eadd9898325b9b2ae67998#6\"}"}'],
            )
            ->execute([]);
    }

    public function testInvalidMessageProvided(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resultExtractionMessageHandlerMock->expects(self::never())->method('__invoke');

        $envelop = new Envelope(
            new DeliveryExecutionClosureMessage(
                'e747ee141e8e-suomynona#ac0ad1ee6ab5#0a92fab3230134cca6eadd9898325b9b2ae67998#6',
                (new DateTime())->getTimestamp(),
            ),
        );
        $this->serializerMock
            ->expects(self::once())
            ->method('decode')
            ->willReturn($envelop);

        $this->eventDispatcherMock
            ->expects(self::never())
            ->method('dispatch');

        (new CommandTester($this->command))
            ->setInputs(
                ['{"body":"{\"id\":\"payloadId\",\"deliveryExecutionId\":\"e747ee141e8e-suomynona#ac0ad1ee6ab5#0a92fab3230134cca6eadd9898325b9b2ae67998#6\"}"}'],
            )
            ->execute([]);
    }
}
