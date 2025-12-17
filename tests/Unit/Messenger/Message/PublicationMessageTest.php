<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Message;

use App\Messenger\Message\PublicationMessage;
use PHPUnit\Framework\TestCase;

class PublicationMessageTest extends TestCase
{
    /** @var PublicationMessage */
    private $subject;

    public function setUp(): void
    {
        $this->subject = new PublicationMessage(
            'publicationId',
            'tenantId',
            'base64/zip/path',
            'http://package/location',
            ['configuration'],
        );
    }

    public function testItCanRetrieveThePublicationId(): void
    {
        $this->assertEquals('publicationId', $this->subject->getPublicationId());
    }

    public function testItCanRetrieveTheTenantId(): void
    {
        $this->assertEquals('tenantId', $this->subject->getTenantId());
    }

    public function testItCanRetrieveTheBase64ZipPath(): void
    {
        $this->assertEquals('base64/zip/path', $this->subject->getBase64ZipPath());
    }

    public function testItCanRetrievePackageRef(): void
    {
        $this->assertEquals('http://package/location', $this->subject->getPackageRef());
    }

    public function testItCanRetrieveTheConfiguration(): void
    {
        $this->assertEquals(['configuration'], $this->subject->getConfiguration());
    }
}
