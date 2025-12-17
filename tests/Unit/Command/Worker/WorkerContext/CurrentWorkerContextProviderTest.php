<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Command\Worker\WorkerContext;

use App\Command\Worker\WorkerContext\CurrentWorkerContext;
use App\Command\Worker\WorkerContext\CurrentWorkerContextProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

class CurrentWorkerContextProviderTest extends TestCase
{
    private WorkerMessageReceivedEvent $workerMessageReceivedEvent;
    private ConsoleCommandEvent $consoleCommandEvent;
    private Command|MockObject $consoleCommandMock;

    private const MESSENGER_RECEIVER = 'receiver';
    private const CONSOLE_COMMAND_NAME = 'command';
    private const CONSOLE_WORKER_COMMAND_NAME = 'worker:command';

    private CurrentWorkerContextProvider $subject;

    protected function setUp(): void
    {
        $this->consoleCommandMock = $this->createMock(Command::class);
        $this->consoleCommandEvent = new ConsoleCommandEvent(
            $this->consoleCommandMock,
            $this->createMock(InputInterface::class),
            $this->createMock(OutputInterface::class),
        );
        $this->workerMessageReceivedEvent = new WorkerMessageReceivedEvent(
            new Envelope(new \stdClass()),
            self::MESSENGER_RECEIVER,
        );

        $this->subject = new CurrentWorkerContextProvider();
    }

    public function testNoExpectedEventsHappen()
    {
        $currentWorkerContext = $this->subject->provide();
        self::assertNull($currentWorkerContext);
    }


    public function testConsoleCommandCaught()
    {
        $this->consoleCommandMock->expects(self::once())->method('getName')->willReturn(self::CONSOLE_COMMAND_NAME);
        $this->subject->onConsoleCommand($this->consoleCommandEvent);
        $currentWorkerContext = $this->subject->provide();

        self::assertInstanceOf(CurrentWorkerContext::class, $currentWorkerContext);
        self::assertEquals(self::CONSOLE_COMMAND_NAME, $currentWorkerContext->getWorkerName());
    }

    public function testConsoleWorkerCommandCaughtAndTrimWorkerPrefix()
    {
        $this->consoleCommandMock
            ->expects(self::once())
            ->method('getName')
            ->willReturn(self::CONSOLE_WORKER_COMMAND_NAME);
        $this->subject->onConsoleCommand($this->consoleCommandEvent);
        $currentWorkerContext = $this->subject->provide();

        self::assertInstanceOf(CurrentWorkerContext::class, $currentWorkerContext);
        self::assertEquals(self::CONSOLE_COMMAND_NAME, $currentWorkerContext->getWorkerName());
    }

    public function testWorkerMessageReceivedCaught(): void
    {
        $this->subject->onWorkerMessageReceived($this->workerMessageReceivedEvent);
        $currentWorkerContext = $this->subject->provide();

        self::assertInstanceOf(CurrentWorkerContext::class, $currentWorkerContext);
        self::assertEquals(self::MESSENGER_RECEIVER, $currentWorkerContext->getWorkerName());
    }

    public function testWorkerMessageReceivedCaughtAfterConsoleCommand(): void
    {
        $this->consoleCommandMock->expects(self::once())->method('getName')->willReturn(self::CONSOLE_COMMAND_NAME);
        $this->subject->onConsoleCommand($this->consoleCommandEvent);

        $this->subject->onWorkerMessageReceived($this->workerMessageReceivedEvent);

        $currentWorkerContext = $this->subject->provide();

        self::assertInstanceOf(CurrentWorkerContext::class, $currentWorkerContext);
        self::assertEquals(self::MESSENGER_RECEIVER, $currentWorkerContext->getWorkerName());
    }
}
