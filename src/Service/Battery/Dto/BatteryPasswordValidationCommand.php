<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Battery\Dto;

class BatteryPasswordValidationCommand
{
    public function __construct(
        public readonly string $password,
        public readonly string $deliveryId,
        public readonly string $deliveryExecutionId,
    ) {
    }
}
