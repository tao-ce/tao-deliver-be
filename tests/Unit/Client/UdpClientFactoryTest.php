<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Client;

use App\Client\UdpClient;
use App\Client\UdpClientFactory;
use PHPUnit\Framework\TestCase;

class UdpClientFactoryTest extends TestCase
{
    /** @var UdpClientFactory */
    private $subject;

    protected function setUp(): void
    {
        $this->subject = new UdpClientFactory();
    }

    public function testCreate(): void
    {
        $this->assertInstanceOf(UdpClient::class, $this->subject->create('0.0.0.0', 8080));
    }
}
