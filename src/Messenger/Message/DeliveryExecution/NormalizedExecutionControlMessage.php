<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use JsonSerializable;

class NormalizedExecutionControlMessage implements JsonSerializable
{
    public function __construct(private readonly DeliveryExecution $deliveryExecution, private readonly array $data)
    {
    }

    public function jsonSerialize(): array
    {
        return array_replace_recursive(
            $this->data,
            ['deliveryExecution' => ['id' => $this->deliveryExecution->getId()]],
        );
    }
}
