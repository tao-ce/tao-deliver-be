<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Generator\Asset;

use App\Generator\Asset\CloudStorageSignedUrlGenerator;
use Google\Auth\SignBlobInterface;
use Google\Cloud\Core\RequestWrapper;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\Connection\Rest;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CloudStorageSignedUrlGeneratorTest extends KernelTestCase
{
    /** @var CloudStorageSignedUrlGenerator */
    private $subject;

    public function setUp(): void
    {
        self::bootKernel();
        $mock = $this->createMock(Rest::class);
        $mockRequestWrapper = $this->createMock(RequestWrapper::class);

        $mockRequestWrapper->method('getCredentialsFetcher')
            ->willReturn($this->createMock(SignBlobInterface::class));

        $mock->method('requestWrapper')->willReturn($mockRequestWrapper);

        $this->subject = new CloudStorageSignedUrlGenerator(
            new Bucket($mock, 'storage'),
            static::getContainer()->getParameter('storage.signed_url.ttl'),
            static::getContainer()->getParameter('asset.url_signature_prefix_name'),
        );
    }

    public function testGenerateDownloadUrl(): void
    {
        $assetPath = 'some/path/file.ext';
        $parsedSignedUrl = parse_url($this->subject->generateDownloadUrl($assetPath));

        self::assertEquals('https', $parsedSignedUrl['scheme']);
        self::assertEquals('storage.googleapis.com', $parsedSignedUrl['host']);
        self::assertEquals('/storage/delivery-execution-uploads/some/path/file.ext', $parsedSignedUrl['path']);
        self::assertMatchesRegularExpression(
            '/^GoogleAccessId=&Expires=(\S+)&Signature=$/',
            $parsedSignedUrl['query'],
        );
    }
}
