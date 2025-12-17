<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA ;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command\Worker;

use App\Command\Worker\Trait\StreamReader;
use App\Messenger\Handler\PlagiarismStatusMessageHandler;
use App\Messenger\Message\HblPlagiarismStatusMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @deprecated Should be used handler instead of command.
 */
#[AsCommand(
    name: PlagiarismStatusWorkerCommand::NAME,
)]
class PlagiarismStatusWorkerCommand extends Command
{
    use StreamReader;

    public const NAME = 'worker:plagiarism-status';

    public function __construct(
        private PlagiarismStatusMessageHandler $handler,
        private SerializerInterface $serializer,
        private EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct(static::NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('Sanctuary wrapper to execute plagiarism status messenger logic.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $envelop = $this->serializer->decode(
            [
                'body' => $this->readPayload($input),
                'headers' => [
                    'type' => HblPlagiarismStatusMessage::class,
                ],
            ],
        );
        $this->eventDispatcher->dispatch(new WorkerMessageReceivedEvent($envelop, static::NAME));

        /** @noinspection PhpParamsInspection */
        $this->handler->__invoke($envelop->getMessage());

        $this->eventDispatcher->dispatch(new WorkerMessageHandledEvent($envelop, static::NAME));

        return Command::SUCCESS;
    }
}
