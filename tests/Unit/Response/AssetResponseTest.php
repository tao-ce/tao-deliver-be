<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Response;

use App\Response\AssetResponse;
use App\Traits\FilesystemTrait;
use Carbon\Carbon;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AssetResponseTest extends TestCase
{
    use FilesystemTrait;

    /** @var Carbon|MockObject */
    private $carbon;

    /** @var AssetResponse */
    private $subject;

    public function setUp(): void
    {
        $this->carbon = $this->createMock(Carbon::class);
        $file = $this->buildPathFor('tests', 'Resources', 'Asset', 'planeStrategy.png');
        $resource = fopen($file, 'rb');

        $this->subject = new AssetResponse(
            $resource,
            'image/png',
            1567687369,
            13360,
        );
    }

    public function testSetContent(): void
    {
        $this->expectException(LogicException::class);
        $this->subject->setContent('someContent');
    }

    public function testIfNoResourceGiven(): void
    {
        $this->expectException(HttpException::class);

        $this->subject = new AssetResponse(
            __FILE__,
            'image/png',
            1567687369,
            13360,
        );
    }

    public function testGetContent(): void
    {
        $this->assertFalse($this->subject->getContent());
    }
}
