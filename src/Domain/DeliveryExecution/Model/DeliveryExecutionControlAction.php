<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model;

use App\TestRunner\Event\Control\ControlStatus;
use App\TestRunner\Event\Control\ControlType;
use JsonSerializable;

class DeliveryExecutionControlAction implements JsonSerializable
{
    public function __construct(
        private readonly ControlType $controlType,
        private readonly ControlStatus $controlStatus,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->controlType,
            'status' => $this->controlStatus,
        ];
    }

    public function getControlType(): ControlType
    {
        return $this->controlType;
    }
}
