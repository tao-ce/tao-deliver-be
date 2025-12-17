<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\tests\Integration\Generator\Asset;

use App\Generator\Asset\LocalSignedUrlGenerator;
use App\Generator\UrlGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

class LocalSignedUrlGeneratorTest extends KernelTestCase
{
    private LocalSignedUrlGenerator $subject;

    protected function setUp(): void
    {
        $this->subject = new LocalSignedUrlGenerator(
            $this::getContainer()->get(UrlGenerator::class),
        );
    }

    public function testGenerateUploadUrl(): void
    {
        $this->assertSame(
            '//tao_deliver_be_nginx/api/v1/attachments/foo',
            $this->subject->generateUploadUrl('foo'),
        );
    }

    public function testGenerateUploadUrlWithPathPrefix(): void
    {
        $this::getContainer()->get(UrlGeneratorInterface::class)->setContext(
            RequestContext::fromUri('https://tao_deliver_be_nginx/bar'),
        );

        $this->assertSame(
            '//tao_deliver_be_nginx/bar/api/v1/attachments/foo',
            $this->subject->generateUploadUrl('foo'),
        );
    }
}
