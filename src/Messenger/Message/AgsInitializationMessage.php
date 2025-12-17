<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message;

use DateTimeInterface;

/**
 * @deprecated Keeping it alive for backward compatibility
 *             with any of the messages that may still be in the queue at the time of deployment.
 */
class AgsInitializationMessage extends AbstractDeliveryExecutionAwareMessage
{
    public function __construct(private DateTimeInterface $timestamp, string $deliveryExecutionId)
    {
        parent::__construct($deliveryExecutionId);
    }

    public function getTimestamp(): DateTimeInterface
    {
        return $this->timestamp;
    }
}
