<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message;

class DataStoreResultMessage
{
    private array $deliveryResult;

    public function __construct(array $deliveryResult)
    {
        $this->deliveryResult = $deliveryResult;
    }

    public function getDeliveryResult(): array
    {
        return $this->deliveryResult;
    }
}
