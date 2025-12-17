<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Delivery\Model\Statistics;

use App\Domain\Delivery\Model\Statistics\DeliveryStatistics;
use PHPUnit\Framework\TestCase;

class DeliveryStatisticsTest extends TestCase
{
    public function testItIsConstructedEmptyByDefault(): void
    {
        $subject = new DeliveryStatistics();

        $this->assertNull($subject->getStatistic('stat'));
        $this->assertEmpty($subject->getStatistics());
    }

    public function testItCanBeConstructedWithGivenStatistics(): void
    {
        $subject = new DeliveryStatistics([
            'stat1' => 10,
            'stat2' => 20,
        ]);

        $this->assertEquals(10, $subject->getStatistic('stat1'));
        $this->assertEquals(20, $subject->getStatistic('stat2'));
        $this->assertNull($subject->getStatistic('stat3'));
    }

    public function testItCanGetAllStatistics(): void
    {
        $subject = new DeliveryStatistics([
            'stat1' => 10,
            'stat2' => 20,
        ]);

        $this->assertEquals(
            [
                'stat1' => 10,
                'stat2' => 20,
            ],
            $subject->getStatistics(),
        );
    }

    public function testItCanSetAndGetAStatistic(): void
    {
        $subject = new DeliveryStatistics();

        $subject->setStatistic('stat', 10);

        $this->assertEquals(10, $subject->getStatistic('stat'));
    }

    public function testItCanIncrementDeclaredStatistic(): void
    {
        $subject = new DeliveryStatistics(['stat' => 10]);

        $subject->incrementStatistic('stat');

        $this->assertEquals(11, $subject->getStatistic('stat'));

        $subject->incrementStatistic('stat');

        $this->assertEquals(12, $subject->getStatistic('stat'));
    }

    public function testItCanIncrementUndeclaredStatistic(): void
    {
        $subject = new DeliveryStatistics();

        $subject->incrementStatistic('stat');

        $this->assertEquals(1, $subject->getStatistic('stat'));

        $subject->incrementStatistic('stat');

        $this->assertEquals(2, $subject->getStatistic('stat'));
    }

    public function testJsonSerialization(): void
    {
        $subject = new DeliveryStatistics([
            'stat1' => 10,
            'stat2' => 20,
        ]);

        $this->assertEquals(
            [
                'stat1' => 10,
                'stat2' => 20,
            ],
            $subject->jsonSerialize(),
        );
    }
}
