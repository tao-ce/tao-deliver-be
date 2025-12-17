<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\Delivery;

use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDelivery;
use App\Service\Delivery\BatteryDeliveryDateFilterService;
use DateTime;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BatteryDeliveryDateFilterServiceTest extends TestCase
{
    private BatteryDeliveryDateFilterService $subject;
    private LoggerInterface $loggerMock;

    public function setUp(): void
    {
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->subject = new BatteryDeliveryDateFilterService($this->loggerMock);
    }

    /**
     * @dataProvider dateTimeProvider
     */
    public function testFilter(bool $result, ?DateTime $start, ?DateTime $end, ?string $timezone): void
    {
        $batteryDelivery = new BatteryDelivery(
            'someUniqueId',
            'password',
            1,
            isset($start) ? $start->getTimestamp() * 1000 : null,
            isset($end) ? $end->getTimestamp() * 1000 : null,
        );
        $battery = new Battery(
            'batteryId',
            'tenantId',
            'batteryName',
            deliveries: [$batteryDelivery],
        );

        $ltiParameters = [
            'custom' => [
                'timezone' => $timezone,
            ],
        ];
        $expected = clone $battery;
        if (!$result) {
            $expected->deliveries = [];
        }

        self::assertEquals($expected, $this->subject->filter($battery, $ltiParameters));
    }

    public function testFilterEmptyBattery(): void
    {
        $battery = new Battery(
            'batteryId',
            'tenantId',
            'batteryName',
        );

        self::assertEquals($battery, $this->subject->filter($battery, []));
    }

    public function dateTimeProvider(): array
    {
        $gmt = new DateTimeZone('GMT');
        return [
            'start and end date are valid' => [
                'result' => true,
                'startDate' => new DateTime('-12 hour', $gmt),
                'endDate' => new DateTime('+1 hour', $gmt),
                'timezone' => 'America/Adak',
            ],
            'start date is invalid' => [
                'result' => false,
                'startDate' => new DateTime('+1 hour', $gmt),
                'endDate' => new DateTime('+1 hour', $gmt),
                'timezone' => 'America/Adak',
            ],
            'end date is invalid' => [
                'result' => false,
                'startDate' => new DateTime('-12 hour', $gmt),
                'endDate' => new DateTime('-15 hour', $gmt),
                'timezone' => 'America/Adak',
            ],
            'start date is valid, end date is not defined' => [
                'result' => true,
                'startDate' => new DateTime('-12 hour', $gmt),
                'endDate' => null,
                'timezone' => 'America/Adak',
            ],
            'start date is not defined, end date is valid' => [
                'result' => true,
                'startDate' => null,
                'endDate' => new DateTime('+1 hour', $gmt),
                'timezone' => 'America/Adak',
            ],
            'neither start nor end date are defined' => [
                'result' => true,
                'startDate' => null,
                'endDate' => null,
                'timezone' => 'America/Adak',
            ],
            'neither start nor end date nor timezone are defined' => [
                'result' => true,
                'startDate' => null,
                'endDate' => null,
                'timezone' => null,
            ],
            'timezone are defined with unknown timezone, neither start nor end date are NOT defined' => [
                'result' => true,
                'startDate' => null,
                'endDate' => null,
                'timezone' => 'FarFarAway/Galaxy',
            ],
            'timezone not defined, start and end date are valid' => [
                'result' => false,
                'startDate' => new DateTime('-12 hour', $gmt),
                'endDate' => new DateTime('+1 hour', $gmt),
                'timezone' => null,
            ],
        ];
    }

    public function invalidEpochProvider()
    {
        $date = new DateTime('now', new DateTimeZone('GMT'));
        return [
            'start date is invalid' => [
                'result' => false,
                'start' => 'invalid',
                'end' => (string)$date->modify('+1 hour')->getTimestamp(),
            ],
            'end date is invalid' => [
                'result' => false,
                'start' => (string)$date->modify('-10 hour')->getTimestamp(),
                'end' => 'invalid',
            ],
            'end and start date is invalid' => [
                'result' => false,
                'start' => 'invalid',
                'end' => 'invalid',
            ],
        ];
    }
}
