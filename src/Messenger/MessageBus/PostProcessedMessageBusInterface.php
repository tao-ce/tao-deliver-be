<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Messenger\MessageBus;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @author Shtykhno Vitalii <vitalii.shtykhno@taotesting.com>
 */
interface PostProcessedMessageBusInterface extends MessageBusInterface
{
    /**
     * When this method called we send all stored messages
     */
    public function free(): array;
}
