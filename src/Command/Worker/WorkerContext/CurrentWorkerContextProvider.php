<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command\Worker\WorkerContext;

use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * @deprecated Should be removed with worker commands
 */
class CurrentWorkerContextProvider implements EventSubscriberInterface
{
    private const WORKER_COMMAND_PREFIX = 'worker:';

    private static ?CurrentWorkerContext $currentWorkerContext = null;

    public function provide(): ?CurrentWorkerContext
    {
        return self::$currentWorkerContext;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleCommandEvent::class => 'onConsoleCommand',
            WorkerMessageReceivedEvent::class => 'onWorkerMessageReceived',
        ];
    }

    public function onConsoleCommand(ConsoleCommandEvent $consoleCommandEvent): void
    {
        self::$currentWorkerContext = new CurrentWorkerContext(
            $this->trimWorkerPrefixIfProvided($consoleCommandEvent->getCommand()->getName()),
        );
    }

    public function onWorkerMessageReceived(WorkerMessageReceivedEvent $workerMessageReceivedEvent): void
    {
        self::$currentWorkerContext = new CurrentWorkerContext(
            $this->trimWorkerPrefixIfProvided($workerMessageReceivedEvent->getReceiverName()),
        );
    }

    private function trimWorkerPrefixIfProvided(string $name): string
    {
        if (str_starts_with($name, self::WORKER_COMMAND_PREFIX)) {
            $name = substr($name, strlen(self::WORKER_COMMAND_PREFIX));
        }

        return $name;
    }
}
