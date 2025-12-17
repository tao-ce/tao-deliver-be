<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Traits;

trait MemoryLeaksTrait
{
    public const ALLOWED_MEMORY_LEAKS = 1024 * 1024;
    protected function measureLeakForMethod(mixed $obj, string $method, array $argArray): void
    {
        gc_collect_cycles();
        $startMemoryUsage = memory_get_usage();
        while ($arg = array_shift($argArray)) {
            $arg = is_array($arg) ? $arg : [$arg];
            call_user_func([$obj, $method], ...$arg);
        }
        gc_collect_cycles();
        $endMemoryUsage = memory_get_usage();

        $this->assertLessThan(
            $startMemoryUsage + self::ALLOWED_MEMORY_LEAKS,
            $endMemoryUsage,
            'Memory usage increased significantly indicating a potential memory leak.',
        );
    }
}
