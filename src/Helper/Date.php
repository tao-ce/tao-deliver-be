<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Helper;

use Carbon\Carbon;
use DateTimeInterface;
use DateTimeZone;

class Date extends Carbon
{
    public const DEFAULT_FORMAT = DateTimeInterface::RFC3339_EXTENDED;

    protected static $strictModeEnabled = false;

    public static function createFromDefaultFormat(
        int|string|null $time = null,
        string|DateTimeZone|null $timezone = null,
    ): ?static {
        if (empty($time)) {
            return null;
        }

        return static::createFromFormat(self::DEFAULT_FORMAT, $time, $timezone)
            ?: static::createFromTimestamp($time, $timezone);
    }
}
