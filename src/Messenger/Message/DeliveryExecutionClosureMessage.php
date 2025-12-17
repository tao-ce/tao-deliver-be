<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message;

class DeliveryExecutionClosureMessage extends AbstractDeliveryExecutionAwareMessage
{
    public function __construct(string $deliveryExecutionId, private int $closeAt)
    {
        parent::__construct($deliveryExecutionId);
    }

    public function getCloseAt(): int
    {
        return $this->closeAt;
    }
}
