<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA ;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\Asset;

use App\Service\Asset\FileSizeNormalizer;
use PHPUnit\Framework\TestCase;

class FileSizeNormalizerTest extends TestCase
{
    private FileSizeNormalizer $sut;

    protected function setUp(): void
    {
        $this->sut = new FileSizeNormalizer();
    }

    /**
     * @dataProvider byteConversionDataProvider
     */
    public function testSizeToBytesWhenInputIsValidThenBytesCalculatedCorrectly($fileSizeLimit, $expectedSize)
    {
        $this->assertEquals($expectedSize, $this->sut->sizeToBytes($fileSizeLimit));
    }

    /**
     * @dataProvider invalidSizeDataProvider
     */
    public function testSizeToBytesWhenInputInvalidThenExceptionThrown($limit)
    {
        $this->expectException(\RuntimeException::class);
        $this->sut->sizeToBytes($limit);
    }

    public function byteConversionDataProvider(): array
    {
        return [
            ['2k', 2000],
            ['2m', 2000000],
            ['2g', 2000000000],
            ['2ki', 2048],
            ['2mi', 2097152],
            ['2gi', 2147483648],
        ];
    }

    public function invalidSizeDataProvider(): array
    {
        return [
            ['2.1'],
            ['2T'],
            ['2.1M'],
            ['2i'],
        ];
    }
}
