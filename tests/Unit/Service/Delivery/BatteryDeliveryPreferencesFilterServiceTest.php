<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\Delivery;

use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDelivery;
use App\Lti\LtiCustomSettings;
use App\Service\Delivery\BatteryDeliveryPreferencesFilterService;
use App\Service\Lti\LtiTokenResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BatteryDeliveryPreferencesFilterServiceTest extends TestCase
{
    private BatteryDeliveryPreferencesFilterService $subject;

    public function setUp(): void
    {
        $this->subject = new BatteryDeliveryPreferencesFilterService(
            new LtiCustomSettings($this->createMock(LtiTokenResolver::class)),
        );
    }

    public function testFilterWithoutSpecifiedDelivery(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $battery = $this->createBattery(['id1', 'id2']);
        $this->subject->filter($battery, []);
    }

    public function testFilterWithSpecifiedDelivery(): void
    {
        $battery = $this->createBattery(['id1', 'id2', 'id3']);
        $expected = clone $battery;
        $expected->deliveries = [$battery->getDelivery('id2')];
        $this->assertEquals(
            $expected,
            $this->subject->filter(
                $battery,
                ['custom' => [LtiCustomSettings::PARAM_BATTERY_DELIVERY_ID => 'id2']],
            ),
        );
    }

    public function testFilterWithInvalidDeliverySpecified(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->subject->filter(
            $this->createBattery(['id1', 'id2']),
            ['custom' => [LtiCustomSettings::PARAM_BATTERY_DELIVERY_ID => 'invalid']],
        );
    }

    public function testFilterInNonSpecificDeliveryMode(): void
    {
        $battery = $this->createBattery(['id1', 'id2'], Battery::MODE_ALL_IN_SEQUENCE);
        $this->assertEquals(
            $battery,
            $this->subject->filter(
                $battery,
                ['custom' => [LtiCustomSettings::PARAM_BATTERY_DELIVERY_ID => 'id2']],
            ),
        );
    }

    private function createBattery(array $deliveryIds, string $mode = Battery::MODE_PREFERRED_DELIVERY): Battery
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
