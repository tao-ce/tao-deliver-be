<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Generator\Asset;

use App\Generator\Asset\LocalSignedUrlGenerator;
use App\Generator\UrlGenerator;
use App\Service\ApplicationInfoService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LocalSignedUrlGeneratorTest extends KernelTestCase
{
    private LocalSignedUrlGenerator $subject;

    public function setUp(): void
    {
        static::bootKernel();

        $getApplicationInfoServiceMock = $this->createMock(ApplicationInfoService::class);
        $getApplicationInfoServiceMock
            ->method('getBackendUrl')
            ->willReturn(str_replace(getenv('DELIVER_BACKEND_URL'), 'http://', '//'));

        $this->subject = new LocalSignedUrlGenerator(
            static::getContainer()->get(UrlGenerator::class),
        );
    }

    public function testGenerateDownloadUrl(): void
    {
        $assetPath = 'somepath/someAsset';

        $urlParameters = [
            'path' => $assetPath,
        ];

        $generatedUrl = $this->subject->generateDownloadUrl($assetPath);

        $this->assertEquals(
            sprintf('%s/api/v1/asset?%s', '//tao_deliver_be_nginx', http_build_query($urlParameters)),
            $generatedUrl,
        );
    }

    public function testGenerateUploadUrl(): void
    {
        $path = 'test/path';

        $this->assertEquals(
            sprintf('%s/api/v1/attachments/%s', '//tao_deliver_be_nginx', $path),
            $this->subject->generateUploadUrl($path),
        );
    }
}
