<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\Asset;

use App\Service\Asset\MimeTypeDetectorService;
use League\Flysystem\FilesystemReader;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use PHPUnit\Framework\TestCase;

class MimeTypeDetectorServiceTest extends TestCase
{
    public function testItWillFindTheCorrectMimeType(): void
    {
        $path = 'test.css';
        $expectedMimeType = 'text/css';
        $storage = $this->createMock(FilesystemReader::class);
        $storage
            ->method('mimeType')
            ->with($path)
            ->willReturn($expectedMimeType);
        $subject = new MimeTypeDetectorService(
            new ExtensionMimeTypeDetector(),
        );

        $this->assertEquals($expectedMimeType, $subject->detect($storage, $path));
    }

    /**
     * @dataProvider mimeTypeDataProvider
     */
    public function testItWillFindTheCorrectMimeTypeAfterFileExtensionChecking(
        string $path,
        string $mimeTypeFromStorage,
        string $expectedMimeType,
    ): void {
        $storage = $this->createMock(FilesystemReader::class);
        $storage
            ->method('mimeType')
            ->with($path)
            ->willReturn($mimeTypeFromStorage);
        $subject = new MimeTypeDetectorService(
            new ExtensionMimeTypeDetector(),
        );

        $this->assertEquals($expectedMimeType, $subject->detect($storage, $path));
    }

    public function mimeTypeDataProvider(): array
    {
        return [
            ['test.css', 'text/plain', 'text/css'],
            ['test.txt', 'text/plain', 'text/plain'],
            ['test.js', 'text/html', 'application/javascript'],
            ['test.doc', 'application/msword', 'application/msword'],
        ];
    }
}
