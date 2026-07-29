<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message\DataPolicy;

use App\Messenger\Message\DataPolicy\ConfirmationMessage;
use App\Messenger\Message\DataPolicy\ValidationRequestMessage;

readonly class ValidationConfirmationMessage extends ConfirmationMessage
{
    public static function createRemovalConfirmationMessage(
        ValidationRequestMessage $requestMessage,
    ): self {
        return new static(
            $requestMessage->tenantId,
            $requestMessage->userId,
            $requestMessage->policyId,
            $requestMessage->policyVersion,
            $requestMessage->ownerApp,
        );
    }
}
