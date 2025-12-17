<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Tests\Unit\Service\Locale;

use App\Service\Locale\LocaleRetriever;
use PHPUnit\Framework\TestCase;

class LocaleRetrieverTest extends TestCase
{
    public function testGetDefaultLocaleReturnsCorrectLocale()
    {
        $defaultLocale = 'en-US';
        $retriever = new LocaleRetriever($defaultLocale);

        $this->assertEquals($defaultLocale, $retriever->getDefaultLocale());
    }

    public function testGetDefaultLocaleWithDifferentLocale()
    {
        $defaultLocale = 'fr-FR';
        $retriever = new LocaleRetriever($defaultLocale);

        $this->assertEquals($defaultLocale, $retriever->getDefaultLocale());
    }
}
