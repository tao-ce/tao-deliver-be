<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Middleware;

use App\Messenger\Message\DeliveryExecutionAcsLogMessage;
use OAT\Library\GraderMessengerBridge\Message\Event\DeliveryPublishedEvent;
use OAT\Library\GraderMessengerBridge\Message\Event\DeliveryExecutionFinishedEvent;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

/**
 * If we are publishing a message to another Symfony application, its worker will try to lookup the bus based
 * on the provided `BusNameStamp`, because the `messenger:consume` command is using `RoutableMessageBus`.
 *
 * Since the value of the `BusNameStamp` contains the name of the sender bus, Messenger won't able to handle it
 * on the receiver side. Without the `BusNameStamp`, the `RoutableMessageBus` will fallback to the default bus.
 */
class RemoveBusNameStampFromNonInternalMessagesMiddleware implements MiddlewareInterface
{
    private const NON_INTERNAL_MESSAGE_TYPES = [
        DeliveryExecutionFinishedEvent::class,
        DeliveryExecutionAcsLogMessage::class,
        DeliveryPublishedEvent::class,
    ];

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (in_array(get_class($envelope->getMessage()), self::NON_INTERNAL_MESSAGE_TYPES)) {
            $envelope = $envelope->withoutStampsOfType(BusNameStamp::class);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
