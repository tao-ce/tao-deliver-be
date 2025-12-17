<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Tests\Unit\Validator\Locale;

use PHPUnit\Framework\TestCase;
use App\Validator\Locale\LocaleValidator;
use InvalidArgumentException;

class LocaleValidatorTest extends TestCase
{
    private LocaleValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new LocaleValidator();
    }

    public function testValidLocaleWithoutRegion()
    {
        $this->validator->validate('en');
        $this->assertTrue(true);
    }

    public function testValidLocaleWithRegion()
    {
        $this->validator->validate('en-US');
        $this->assertTrue(true);
    }

    public function testInvalidLocaleWithNumbers()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Locale [en123] has invalid format');
        $this->validator->validate('en123');
    }

    public function testLocaleWithLowercaseRegion()
    {
        $this->validator->validate('en-us');
        $this->assertTrue(true);
    }

    public function testLocaleAsNull()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Locale must be a string, [NULL] given.');
        $this->validator->validate(null);
    }
}
