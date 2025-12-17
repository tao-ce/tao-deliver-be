<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command\Worker;

use App\Command\Worker\Trait\StreamReader;
use App\Messenger\Handler\DataStoreMessageHandler;
use App\Messenger\Message\DataStoreMessage;
use InvalidArgumentException;
use JsonException;
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
    name: DataStoreWorkerCommand::NAME,
)]
class DataStoreWorkerCommand extends Command
{
    use StreamReader;

    public const NAME = 'worker:datastore';

    public function __construct(
        private DataStoreMessageHandler $handler,
        private SerializerInterface $serializer,
        private EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct(static::NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('Sanctuary wrapper to execute datastore messenger logic.');
    }

    /**
     * @throws JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $envelop = $this->serializer->decode(
            $this->readDecodedPayload($input),
        );
        $this->eventDispatcher->dispatch(new WorkerMessageReceivedEvent($envelop, static::NAME));

        $message = $envelop->getMessage();
        if (!$message instanceof DataStoreMessage) {
            throw new InvalidArgumentException(sprintf('Unexpected message %s provided to %s', $message::class, static::NAME));
        }
        $this->handler->__invoke($message);

        $this->eventDispatcher->dispatch(new WorkerMessageHandledEvent($envelop, static::NAME));

        return parent::SUCCESS;
    }
}
