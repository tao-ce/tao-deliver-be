<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Validator\Locale;

use InvalidArgumentException;

class LocaleValidator
{
    public function validate(mixed $locale): void
    {
        $pattern = '/^[a-zA-Z](-?[a-zA-Z]+)*$/';

        if (!is_string($locale)) {
            throw new InvalidArgumentException(
                sprintf("Locale must be a string, [%s] given.", gettype($locale)),
            );
        }

        if (preg_match($pattern, $locale) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Locale [%s] has invalid format', $locale),
            );
        }
    }
}
