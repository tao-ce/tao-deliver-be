<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Traits;

use App\Tests\Helpers\ContainerAwareTestingHelper;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;

trait MessengerTestingTrait
{
    /** @var MessageBusInterface */
    private $testMessageBus;

    /** @var CommandTester */
    private $testMessageBusCommandTester;

    protected function setUp(): void
    {
        $this->setUpTestMessageBus();
    }

    protected function setUpTestMessageBus(): void
    {
        ContainerAwareTestingHelper::checkKernelTestCase(static::class);

        $this->testMessageBus = static::getContainer()->get(MessageBusInterface::class);
        $this->testMessageBusCommandTester = new CommandTester(
            (new Application(static::$kernel))->find(ConsumeMessagesCommand::getDefaultName()),
        );
    }

    protected function publishMessage($message): Envelope
    {
        return $this->testMessageBus->dispatch($message);
    }

    /**
     * @param string $transportName
     * @param int $messagesLimit
     * @param int $timeLimit
     * @param int $noReset     - disable clear cache after test worker stop
     */
    protected function consumeTransportMessages(string $transportName, int $messagesLimit = 1, int $timeLimit = 1, int $noReset = 0): void
    {
        try {
            $this->testMessageBusCommandTester->execute([
                'receivers' => [$transportName],
                '--limit' => $messagesLimit,
                '--limit' => $messagesLimit,
                '--no-reset' => $noReset,
            ]);
        } catch (Throwable $exception) {
            $this->fail($exception->getMessage());
        }
    }

    protected function getTransport(string $transportName): TransportInterface
    {
        return static::getContainer()->get('messenger.transport.' . $transportName);
    }

    /**
     * @return Envelope[]
     */
    protected function getTransportMessages(string $transportName): array
    {
        return $this->getTransport($transportName)->get();
    }

    protected function assertCountTransportMessages(string $transportName, int $messageCount): void
    {
        $this->assertCount($messageCount, $this->getTransportMessages($transportName));
    }

    protected function assertHasTransportMessage(string $transportName, string $messageClass): void
    {
        foreach ($this->getTransportMessages($transportName) as $envelope) {
            if ($envelope->getMessage() instanceof $messageClass) {
                return;
            }
        }

        $this->fail(sprintf('Can not find a message with class: %s in transport: %s', $messageClass, $transportName));
    }

    protected function assertHasNoTransportMessage(string $transportName, string $messageClass): void
    {
        foreach ($this->getTransportMessages($transportName) as $envelope) {
            if ($envelope->getMessage() instanceof $messageClass) {
                $this->fail(sprintf('Found a message with class: %s in transport: %s', $messageClass, $transportName));
            }
        }
    }
}
