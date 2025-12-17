<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Domain\DeliveryExecution\Model\ExtraStateData\DurationStorage;

use App\Domain\DeliveryExecution\Model\ExtraStateData\DurationStorage\DurationInterface;
use App\Domain\DeliveryExecution\Model\ExtraStateData\DurationStorage\ServerDuration;
use App\Tests\Traits\DomainTestingTrait;
use PHPUnit\Framework\TestCase;

class ServerDurationTest extends TestCase
{
    use DomainTestingTrait;

    /** @var ServerDuration */
    private $subject;

    protected function setUp(): void
    {
        $this->subject = $this->createTestServerDuration();
    }

    public function testItImplementsDurationInterface(): void
    {
        $this->assertInstanceOf(DurationInterface::class, $this->subject);
    }

    public function testGetQtiComponentIdentifier(): void
    {
        $this->assertEquals('qtiComponentIdentifier', $this->subject->getQtiComponentIdentifier());
    }

    public function testGetStartedAt(): void
    {
        $this->assertEquals(12.34, $this->subject->getStartedAt());
    }

    public function testGetEndedAtIfNotEnded(): void
    {
        $this->assertEquals(null, $this->subject->getEndedAt());
    }

    public function testGetEndedAt(): void
    {
        $this->subject = $this->createTestServerDuration(
            'id',
            10.50,
            15.50,
        );

        $this->assertEquals(15.50, $this->subject->getEndedAt());
    }

    public function testGetDurationIfNotEnded(): void
    {
        $this->assertEquals(0.0, $this->subject->getDuration());
    }

    public function testGetDuration(): void
    {
        $this->subject = $this->createTestServerDuration(
            'id',
            10.50,
            15.50,
        );

        $this->assertEquals(5.0, $this->subject->getDuration());
    }

    public function testSetEndedAt(): void
    {
        $withEndedAt = $this->subject->withEndedAt(15.50);

        $this->assertNotSame($this->subject, $withEndedAt);
        $this->assertEquals(15.50, $withEndedAt->getEndedAt());
    }

    public function testIsEnded(): void
    {
        $this->assertFalse($this->subject->isEnded());

        $withEndedAt = $this->subject->withEndedAt(15.50);

        $this->assertTrue($withEndedAt->isEnded());
    }
}
