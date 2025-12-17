<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Command\Worker;

use App\Command\Worker\ItemExternalScoringWorkerCommand;
use App\Messenger\Handler\ItemExternalScoringHandler;
use App\Messenger\Message\DeliveryExecutionItemExternalScoringMessage;
use App\Tests\Traits\DomainTestingTrait;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

class ItemExternalScoringWorkerCommandTest extends KernelTestCase
{
    use DomainTestingTrait;

    private ItemExternalScoringHandler|MockObject $externalItemHandlerMock;
    private EventDispatcherInterface|MockObject $eventDispatcherMock;

    private ItemExternalScoringWorkerCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        static::bootKernel();

        $this->externalItemHandlerMock = $this->getMockBuilder(ItemExternalScoringHandler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);

        $this->command = new ItemExternalScoringWorkerCommand(
            $this->externalItemHandlerMock,
            $this->eventDispatcherMock,
        );
    }

    public function testItCanExtractExternalScoreMessage(): void
    {
        $this->externalItemHandlerMock->expects(self::once())->method('__invoke');

        $envelop = new Envelope(
            DeliveryExecutionItemExternalScoringMessage::fromArray(
                json_decode($this->getPayloadJson(), true),
            ),
        );

        $this->eventDispatcherMock
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [new WorkerMessageReceivedEvent($envelop, ItemExternalScoringWorkerCommand::NAME)],
                [new WorkerMessageHandledEvent($envelop, ItemExternalScoringWorkerCommand::NAME)],
            );

        (new CommandTester($this->command))
            ->setInputs([$this->getPayloadJson()])
            ->execute([]);
    }

    public function testInvalidMessageProvided(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->externalItemHandlerMock->expects(self::never())->method('__invoke');

        $this->eventDispatcherMock
            ->expects(self::never())
            ->method('dispatch');

        (new CommandTester($this->command))
            ->setInputs(['{}'])
            ->execute([]);
    }

    private function getPayloadJson(): string
    {
        return file_get_contents(
            __DIR__ . '/../../../Resources/Payload/DeliveryExecutionItemExternalScoringMessage.json',
        );
    }
}
