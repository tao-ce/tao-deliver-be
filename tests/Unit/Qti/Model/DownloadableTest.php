<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Tests\Unit\Qti\Model;

use App\Qti\Model\Asset;
use App\Qti\Model\DownloadableAsset;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use qtism\common\enums\BaseType;
use qtism\common\enums\Cardinality;

class DownloadableTest extends TestCase
{
    private const DOWNLOAD_URL = 'http://localhost/file';
    private const DOWNLOAD_PATH = 'path1/path2';

    private Asset|MockObject $assetMock;
    private DownloadableAsset $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assetMock = $this->createMock(Asset::class);
        $this->subject = new DownloadableAsset($this->assetMock, self::DOWNLOAD_URL, self::DOWNLOAD_PATH);
    }

    public function testValidAssetBehavior(): void
    {
        $filename = 'filename';
        $equals = (bool)random_int(0, 1);
        $baseType = BaseType::FILE;
        $cardinality = Cardinality::SINGLE;
        $data = 'data';
        $mimeType = 'mimeType';
        $hasFilename = (bool)random_int(0, 1);
        $stream = fopen('php://memory', 'r');
        $identifier = 'file/path/as/identifier';
        $size = random_int(0, 10);
        $toString = sprintf('%s,%s,base64,encodedDataBase64', $filename, $mimeType);

        $this->assetMock->expects(self::once())->method('getFilename')->willReturn($filename);
        $this->assetMock->expects(self::once())->method('equals')->willReturn($equals);
        $this->assetMock->expects(self::once())->method('getBaseType')->willReturn($baseType);
        $this->assetMock->expects(self::once())->method('getCardinality')->willReturn($cardinality);
        $this->assetMock->expects(self::once())->method('getMimeType')->willReturn($mimeType);
        $this->assetMock->expects(self::once())->method('hasFilename')->willReturn($hasFilename);
        $this->assetMock->expects(self::once())->method('getIdentifier')->willReturn($identifier);
        $this->assetMock->expects(self::once())->method('getData')->willReturn($data);
        $this->assetMock->expects(self::once())->method('getStream')->willReturn($stream);
        $this->assetMock->expects(self::once())->method('getSize')->willReturn($size);
        $this->assetMock->expects(self::once())->method('getValue')->willReturn($data);
        $this->assetMock->expects(self::once())->method('__toString')->willReturn($toString);

        $this->assertEquals($hasFilename, $this->subject->hasFilename());
        $this->assertEquals($filename, $this->subject->getFilename());
        $this->assertEquals($equals, $this->subject->equals($this->assetMock));
        $this->assertEquals($baseType, $this->subject->getBaseType());
        $this->assertEquals($cardinality, $this->subject->getCardinality());
        $this->assertEquals($mimeType, $this->subject->getMimeType());
        $this->assertEquals($identifier, $this->subject->getIdentifier());
        $this->assertEquals($data, $this->subject->getData());
        $this->assertEquals($data, $this->subject->getValue());
        $this->assertEquals($size, $this->subject->getSize());
        $this->assertEquals($stream, $this->subject->getStream());
        $this->assertEquals(
            sprintf('%s,bucket_path,%s,download_url,%s', $toString, self::DOWNLOAD_PATH, self::DOWNLOAD_URL),
            $this->subject->__toString(),
        );
    }
}
