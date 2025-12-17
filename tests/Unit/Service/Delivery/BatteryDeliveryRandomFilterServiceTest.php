<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\Delivery;

use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDelivery;
use App\Service\Delivery\BatteryDeliveryRandomFilterService;
use PHPUnit\Framework\TestCase;

class BatteryDeliveryRandomFilterServiceTest extends TestCase
{
    private BatteryDeliveryRandomFilterService $subject;

    public function setUp(): void
    {
        $this->subject = new BatteryDeliveryRandomFilterService();
    }

    public function testFilterFromMultipleDeliveries(): void
    {
        $battery = $this->createBattery(['id1', 'id2']);
        $filteredBattery = $this->subject->filter($battery, []);
        $this->assertCount(1, $filteredBattery->deliveries);
    }

    public function testFilterInNonRandomDeliveryMode(): void
    {
        $battery = $this->createBattery(['id1', 'id2'], Battery::MODE_ALL_IN_SEQUENCE);
        $this->assertEquals(
            $battery,
            $this->subject->filter($battery, []),
        );
    }

    public function testFilterEmptyBattery(): void
    {
        $battery = $this->createBattery();
        $this->assertEquals(
            $battery,
            $this->subject->filter($battery, []),
        );
    }

    private function createBattery(array $deliveryIds = [], string $mode = Battery::MODE_RANDOM_DELIVERY): Battery
    {
        return new Battery(
            'batteryId',
            'tenantId',
            'batteryName',
            mode: $mode,
            deliveries: array_map(
                static fn(string $id): BatteryDelivery => new BatteryDelivery(
                    $id,
                    'password',
                    1,
                    null,
                    null,
                ),
                $deliveryIds,
            ),
        );
    }
}
