<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Qti\Model;

use App\Qti\Model\Asset;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use qtism\common\datatypes\files\FileHash;

class AssetTest extends TestCase
{
    private const FILE_TYPE = 'image/jpeg';
    private const FILE_CONTENT_BASE64 = '/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2NjIpLCBxdWFsaXR5ID0gOTAK/9sAQwADAgIDAgIDAwMDBAMDBAUIBQUEBAUKBwcGCAwKDAwLCgsLDQ4SEA0OEQ4LCxAWEBETFBUVFQwPFxgWFBgSFBUU/9sAQwEDBAQFBAUJBQUJFA0LDRQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQU/8AAEQgAKAAoAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A+ZG0zVrC7RIllWNYg+8kjK8ZGex5rZk0q3k+y6vDP51ozbZwoCurZ6lf5nvXP+J/HepaJr6S+cdRs402tbPgIflIwD179TznHWug8G/EXwx4gintY8WL3beSbG4n8tVkOMOGPUdRjjr7V+uxrUfaOk5Wfn+hr7VpHYa9ZJZaMytJGbVANrBhtbcM5Bzz/wDXrnPh+IdK1mxvdQ1QJYTBvNit08yUpyMBOOvHOQBzW34p8M3Ntp9noOoX9tbJbTTeSJrhUGSsbjOT6M2PXGOoryTxX8WdNsEu9L0CA3d9v8lr5VAURqeiEdcnPtWuJxFLD+/Udv1ZKqaWZ6Z4k8SxXup6lc6Xo8kVqxKATEvIU5/AZGOOf8SuG+H/AIt1HWY/7H1KaSCzlzJut4FaQtxwzHBAxnjNFKnP6xFTiy1O3Q6Cx8J2Ovzx2ur2k9vI+CtwmELeg54P55qprvwx8Mw3F1PapdXTgbSpQbM+3P8AWu+sHvL+OO3kW387IRLqWLJHpu9a2/tlrodmtvrNhAzxr5haN9jOQ4EihuQcA55GcDrXVLC0qivOK9WjklPlep49qmqvceEF0qXQIobRJpCw8xywG1Nrf3d2d3boB9av+GfDPhK7srS5/wCEbaJlIW5udz4POOig46+levfbPBkvhpXitrxml/eI115aRNyc/PuJPII4Hb3rIjkyUTTVW3tZds0vlg4JZjlCSfm4weRwSfSpWGi5KUrP5IzUrnQSfDnRrXwumsaWsNjp7gKjwZLyE4OW3KCcAHjjpRUlrC4RLWAD7KAAOcg/h25NFd6gkrErmRiaDcQ3ESzQlHBGSuc4NGv2Ka3JE138qxEsBEoQfoMUUU1qtTeolcns/APgyJYru01u5tL3cGa2mjQiPnJw2OATjGBxzxWm1xaR28lpZ3UcwLBm8s5LEAjr9CfSiinFKOiMHFGnplvGlpmVVXHYn9aKKKqw02f/2Q==';
    private const FILE_NAME = 'file';
    private const FILE_PATH = 'path/to/' . self::FILE_NAME . '/hash';

    private string $fileContent;
    private FilesystemOperator|MockObject $storageMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileContent = base64_decode(self::FILE_CONTENT_BASE64);

        $this->storageMock = $this->createMock(FilesystemOperator::class);
        $this->storageMock
            ->method('read')
            ->willReturnReference($this->fileContent);
        $this->storageMock
            ->method('mimeType')
            ->with(self::FILE_PATH)
            ->willReturn(self::FILE_TYPE);
    }

    public function testItCanCastToString(): void
    {
        $this->assertEquals(
            sprintf('%s,%s,base64,%s', self::FILE_NAME, self::FILE_TYPE, self::FILE_CONTENT_BASE64),
            $this->createSut(),
        );
    }

    public function testItCanCastToStringWithPredefinedMimeType(): void
    {
        $this->assertEquals(
            sprintf('%s,%s,base64,%s', self::FILE_NAME, 'test/test', self::FILE_CONTENT_BASE64),
            $this->createSut(mimeType: 'test/test'),
        );
    }

    public function testItCanCastToStringWithDataSerializationDisabled(): void
    {
        $this->assertEquals(
            self::FILE_NAME,
            $this->createSut(false),
        );
    }

    public function testItCanCastToStringOnUnreadableFile(): void
    {
        $this->storageMock
            ->method('read')
            ->willThrowException(UnableToReadFile::fromLocation(self::FILE_PATH));

        $this->assertEquals(
            self::FILE_NAME,
            $this->createSut(),
        );
    }

    private function createSut(bool $isSerializable = true, string $mimeType = ''): Asset
    {
        return new Asset(
            $isSerializable,
            new FileHash(
                'id',
                $mimeType,
                self::FILE_NAME,
                sha1('id'),
            ),
            self::FILE_PATH,
            $this->storageMock,
        );
    }
}
