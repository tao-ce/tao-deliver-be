<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Event\Control;

use JsonSerializable;

enum ControlType: string implements JsonSerializable
{
    case START = 'start';
    case RESUME = 'resume';
    case PAUSE = 'pause';
    case SUBMISSION = 'submission';
    case TERMINATION = 'termination';
    case FLAG = 'flag';
    case NAVIGATION = 'navigation';

    public function isSecurityEvent(): bool
    {
        return in_array($this, [self::PAUSE, self::FLAG], true);
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
