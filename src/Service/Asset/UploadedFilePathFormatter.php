<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Asset;

class UploadedFilePathFormatter implements UploadedFilePathFormatterInterface
{
    public function format(string ...$pathParts): string
    {
        return implode(DIRECTORY_SEPARATOR, $pathParts);
    }
}
