<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Traits;

trait FilesystemTrait
{
    protected function buildPathFor(...$nodes): string
    {
        return implode(DIRECTORY_SEPARATOR, array_filter($nodes));
    }
}
