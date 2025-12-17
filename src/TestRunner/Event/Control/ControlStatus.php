<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Event\Control;

use JsonSerializable;

enum ControlStatus: string implements JsonSerializable
{
    case FAILED = 'failed';
    case SUCCESS = 'succeeded';

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
