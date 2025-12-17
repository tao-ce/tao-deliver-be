<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\Service;

use App\TestRunner\Service\RealTimeService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RealTimeServiceTest extends KernelTestCase
{
    private readonly RealTimeService $sutWithUrl;
    private readonly RealTimeService $sutWithoutUrl;

    protected function setUp(): void
    {
        $this->sutWithUrl = new RealTimeService('url');
        $this->sutWithoutUrl = new RealTimeService(null);
    }

    public function testGetSocketConnectionUrl(): void
    {
        $this->assertEquals('url', $this->sutWithUrl->getSocketConnectionUrl());
        $this->assertNull($this->sutWithoutUrl->getSocketConnectionUrl());
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->sutWithUrl->isEnabled());
        $this->assertFalse($this->sutWithoutUrl->isEnabled());
    }

    public function testGetConfiguration(): void
    {
        $this->assertEquals(
            ['enabled' => true, 'socketConnectionUrl' => 'url'],
            $this->sutWithUrl->getConfiguration(),
        );
        $this->assertEquals(
            ['enabled' => false, 'socketConnectionUrl' => null],
            $this->sutWithoutUrl->getConfiguration(),
        );
    }
}
