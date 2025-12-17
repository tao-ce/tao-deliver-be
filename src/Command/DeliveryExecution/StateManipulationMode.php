<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command\DeliveryExecution;

enum StateManipulationMode: string
{
    case READ = 'read';
    case MERGE = 'merge';
    case REPLACE = 'replace';
    case PURGE = 'purge';

    public function isReadonly(): bool
    {
        return $this === self::READ;
    }

    public function isPurging(): bool
    {
        return $this === self::PURGE;
    }

    public function isClearing(): bool
    {
        return $this === self::REPLACE || $this->isPurging();
    }
}
