<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command\Worker;

use App\Command\Worker\Trait\StreamReader;
use App\Messenger\Handler\ItemExternalScoringHandler;
use App\Messenger\Message\DeliveryExecutionItemExternalScoringMessage;
use InvalidArgumentException;
use JsonException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @deprecated Should be used handler instead of command.
 */
#[AsCommand(
    name: ItemExternalScoringWorkerCommand::NAME,
)]
class ItemExternalScoringWorkerCommand extends Command
{
    use StreamReader;

    public const NAME = 'worker:item-external-scoring-submission';

    public function __construct(
        private ItemExternalScoringHandler $handler,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct(static::NAME);
    }

    protected function configure(): void
    {
        $this->setDescription('Sanctuary wrapper to execute item external scoring messenger logic.');
    }

    /**
     * @throws JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $decodedMessage = $this->readDecodedPayload($input);

        if (empty($decodedMessage['assessmentResult'])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unexpected message %s provided to %s',
                    DeliveryExecutionItemExternalScoringMessage::class,
                    static::NAME,
                ),
            );
        }

        $message = DeliveryExecutionItemExternalScoringMessage::fromArray($decodedMessage);

        $this->eventDispatcher->dispatch(new WorkerMessageReceivedEvent(Envelope::wrap($message), static::NAME));
        $this->handler->__invoke($message);
        $this->eventDispatcher->dispatch(new WorkerMessageHandledEvent(Envelope::wrap($message), static::NAME));

        return parent::SUCCESS;
    }
}
