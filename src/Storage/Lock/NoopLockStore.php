<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Storage\Lock;

use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

class NoopLockStore implements PersistingStoreInterface
{
    public function save(Key $key): void
    {
        // noop
    }

    public function delete(Key $key): void
    {
        // noop
    }

    public function exists(Key $key): false
    {
        return false;
    }

    public function putOffExpiration(Key $key, float $ttl): void
    {
        // noop
    }
}
