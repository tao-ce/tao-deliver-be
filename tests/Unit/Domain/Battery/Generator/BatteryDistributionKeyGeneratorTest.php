<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Battery\Generator;

use App\Domain\Battery\Generator\BatteryDistributionKeyGenerator;
use PHPUnit\Framework\TestCase;

class BatteryDistributionKeyGeneratorTest extends TestCase
{
    /**
     * @dataProvider batteryDistributionDataProvider
     */
    public function testGenerateBatteryDistributionKey(
        string $expected,
        string $batteryId,
        string $userId,
        ?string $attemptId = null,
    ): void {
        $this->assertEquals(
            $expected,
            BatteryDistributionKeyGenerator::generateBatteryDistributionKey(
                $batteryId,
                $userId,
                $attemptId,
            ),
        );
    }

    public function batteryDistributionDataProvider(): array
    {
        return [
            [
                'expected' => 'dIresu#batteryId#attemptId',
                'batteryId' => 'batteryId',
                'userId' => 'userId',
                'attemptId' => 'attemptId',
            ],
            [
                'expected' => 'OAT#c47b5ed8-14ed-4b34-a433-a6716471d25a',
                'batteryId' => 'c47b5ed8-14ed-4b34-a433-a6716471d25a',
                'userId' => 'TAO',
                'attemptId' => '',
            ],
        ];
    }
}
