<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message;

use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;

class DeliveryExecutionAcsLogMessage
{
    public function __construct(
        public readonly string $deliveryExecutionId,
        public readonly ?string $itemId,
        public readonly string $status,
        public readonly AcsControlInterface|array $acsControl,
    ) {
    }
}
