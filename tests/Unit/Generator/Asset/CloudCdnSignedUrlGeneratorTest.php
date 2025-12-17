<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Generator\Asset;

use App\Generator\Asset\CloudCdnSignedUrlGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Cache\CacheInterface;

class CloudCdnSignedUrlGeneratorTest extends KernelTestCase
{
    private CacheInterface|MockObject $cacheMock;
    private CloudCdnSignedUrlGenerator $subject;

    public function setUp(): void
    {
        self::bootKernel();
        $this->cacheMock = $this->createMock(CacheInterface::class);
        $this->subject = new CloudCdnSignedUrlGenerator(
            static::getContainer()->getParameter('asset.ttl'),
            getenv('GOOGLE_CLOUD_CDN_URL'),
            getenv('GOOGLE_CLOUD_CDN_KEY_NAME'),
            getenv('GOOGLE_CLOUD_CDN_KEY'),
            60,
            $this->cacheMock,
        );
    }

    public function testGenerateDownloadUrl(): void
    {
        $assetPath = 'some/path/file.ext';
        $parsedCloudCdnUrl = parse_url(getenv('GOOGLE_CLOUD_CDN_URL'));
        $parsedSignedUrl = parse_url($this->subject->generateDownloadUrl($assetPath));

        self::assertEquals($parsedCloudCdnUrl['scheme'], $parsedSignedUrl['scheme']);
        self::assertEquals($parsedCloudCdnUrl['host'], $parsedSignedUrl['host']);
        self::assertEquals('/' . $assetPath, $parsedSignedUrl['path']);
        self::assertMatchesRegularExpression(
            sprintf('/^Expires=(\S+)&KeyName=%s&Signature=(\S+)$/', getenv('GOOGLE_CLOUD_CDN_KEY_NAME')),
            $parsedSignedUrl['query'],
        );
    }

    public function testGenerateWithSpecialChars(): void
    {
        $assetPath = 'some/path/Prøveprosjekt.ext';
        $expectedPath = 'some/path/Pr%C3%B8veprosjekt.ext';

        $parsedCloudCdnUrl = parse_url(getenv('GOOGLE_CLOUD_CDN_URL'));
        $parsedSignedUrl = parse_url($this->subject->generateDownloadUrl($assetPath));

        self::assertEquals($parsedCloudCdnUrl['scheme'], $parsedSignedUrl['scheme']);
        self::assertEquals($parsedCloudCdnUrl['host'], $parsedSignedUrl['host']);
        self::assertEquals('/' . $expectedPath, $parsedSignedUrl['path']);
        self::assertMatchesRegularExpression(
            sprintf('/^Expires=(\S+)&KeyName=%s&Signature=(\S+)$/', getenv('GOOGLE_CLOUD_CDN_KEY_NAME')),
            $parsedSignedUrl['query'],
        );
    }
}
