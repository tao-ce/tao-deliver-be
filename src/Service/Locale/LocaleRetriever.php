<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Service\Locale;

class LocaleRetriever
{
    public function __construct(private readonly string $defaultLocale)
    {
    }

    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }
}
