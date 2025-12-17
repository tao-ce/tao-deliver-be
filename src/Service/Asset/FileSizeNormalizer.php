<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA ;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Asset;

use RuntimeException;

class FileSizeNormalizer
{
    /**
     * @throws RuntimeException
     */
    public function sizeToBytes($maxSize): int
    {
        if (ctype_digit((string)$maxSize)) {
            return (int)$maxSize;
        }

        if (preg_match('/^(\d++)([kmg])(i?)$/i', $maxSize, $matches)) {
            $multiplier = !empty($matches[3]) ? 1024 : 1000;
            $size = $matches[1];
            switch (strtolower($matches[2])) {
                case 'g':
                    return $size * ($multiplier ** 3);
                case 'm':
                    return $size * ($multiplier ** 2);
                case 'k':
                    return $size * $multiplier;
            }
        } else {
            throw new RuntimeException(sprintf('"%s" is not a valid maximum size.', $maxSize));
        }
    }
}
