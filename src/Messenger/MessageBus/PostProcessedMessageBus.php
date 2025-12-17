<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\MessageBus;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @author Shtykhno Vitalii <vitalii.shtykhno@taotesting.com>
 */
class PostProcessedMessageBus implements PostProcessedMessageBusInterface
{
    /**
     * @var array of message objects and their stamps
     */
    private array $dispatchWaitingList = [];

    public function __construct(private MessageBusInterface $messageBus)
    {
    }

    /**
     * create real messages from wait list
     *
     * @return Envelope[]
     */
    public function free(): array
    {
        $dispatchList = [];
        while ([$message, $stamps] = array_shift($this->dispatchWaitingList)) {
            $dispatchList[] = $this->messageBus->dispatch($message, $stamps);
        }

        return $dispatchList;
    }

    /**
     * store message to waiting list until it will not be removed manually
     *     or by specific message
     */
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $sliceKey = $this->createKeyFromMessage($message);
        $this->dispatchWaitingList[$sliceKey] = [$message, $stamps];

        return Envelope::wrap($message, $stamps);
    }

    public function getDispatchWaitingList(): array
    {
        return $this->dispatchWaitingList;
    }

    /**
     * transform object to primitive uniq string for future storage and utilization
     */
    private function createKeyFromMessage(object $message): int
    {
        return spl_object_id($message);
    }
}
