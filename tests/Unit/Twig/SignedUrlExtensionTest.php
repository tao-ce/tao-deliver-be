<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Generator\Asset\CloudCdnSignedUrlGenerator;
use App\Registry\SignedUrlGeneratorRegistry;
use App\Twig\SignedUrlExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

class SignedUrlExtensionTest extends TestCase
{
    /** @var SignedUrlGeneratorRegistry|MockObject */
    private $signedUrlGeneratorRegistryMock;

    /** @var SignedUrlExtension */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signedUrlGeneratorRegistryMock = $this->createMock(SignedUrlGeneratorRegistry::class);

        $this->subject = new SignedUrlExtension($this->signedUrlGeneratorRegistryMock);
    }

    public function testGetFunctions(): void
    {
        $this->assertEquals(
            [
                new TwigFunction('signAssetUrl', [$this->subject, 'signAssetUrl']),
            ],
            $this->subject->getFunctions(),
        );
    }

    public function testSignAssetUrl(): void
    {
        $signedUrlGeneratorMock = $this->createMock(CloudCdnSignedUrlGenerator::class);

        $signedUrlGeneratorMock
            ->expects($this->once())
            ->method('generateDownloadUrl')
            ->with('assetPath')
            ->willReturn('signedUrl');

        $this->signedUrlGeneratorRegistryMock
            ->expects($this->once())
            ->method('getGenerator')
            ->with(CloudCdnSignedUrlGenerator::NAME)
            ->willReturn($signedUrlGeneratorMock);

        $this->assertEquals('signedUrl', $this->subject->signAssetUrl('assetPath'));
    }
}
