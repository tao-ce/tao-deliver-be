<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Battery\Model;

use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDistribution;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BatteryDistributionTest extends TestCase
{
    private BatteryDistribution $sut;
    private Battery|MockObject $battery;

    protected function setUp(): void
    {
        $this->battery = $this->createMock(Battery::class);
        $this->sut = new BatteryDistribution('id', 'userId', $this->battery);
    }

    public function testItCanRetrieveTheId(): void
    {
        $this->assertSame('id', $this->sut->getId());
    }

    public function testItCanRetrieveTheUserId(): void
    {
        $this->assertSame('userId', $this->sut->userId);
    }

    public function testItCanRetrieveTheBatteryId(): void
    {
        $this->assertSame($this->battery, $this->sut->battery);
    }

    public function testLocaleIsInitiallyNull(): void
    {
        $this->assertNull($this->sut->getLocale());
    }

    public function testCanSetAndGetLocale(): void
    {
        $locale = 'en-US';
        $this->sut->setLocale($locale);
        $this->assertSame($locale, $this->sut->getLocale());
    }

    public function testAddUpdateIsCalledWhenLocaleIsSet(): void
    {
        $sut = $this->getMockBuilder(BatteryDistribution::class)
            ->setConstructorArgs(['id', 'userId', $this->battery])
            ->onlyMethods(['addUpdate'])
            ->getMock();

        $sut->expects($this->once())
            ->method('addUpdate')
            ->with('locale');

        $sut->setLocale('en-US');
    }
}
