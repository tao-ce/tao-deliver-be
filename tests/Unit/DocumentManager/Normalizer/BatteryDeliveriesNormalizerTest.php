<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Normalizer;

use App\DocumentManager\Normalizer\BatteryDeliveriesNormalizer;
use App\Domain\Battery\Model\BatteryDelivery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BatteryDeliveriesNormalizerTest extends TestCase
{
    private LoggerInterface|MockObject $logger;
    private BatteryDeliveriesNormalizer $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subject = new BatteryDeliveriesNormalizer($this->logger);
    }

    public function testDenormalize(): void
    {
        $deliveries = [
            [
                'id' => 'deliveryId',
                'password' => 'deliveryPassword',
                'order' => 1,
            ],
        ];

        $this->logger
            ->expects($this->never())
            ->method('error');

        $batteryDeliveries = $this->subject->denormalize($deliveries);

        $this->assertCount(1, $batteryDeliveries);
        $this->assertInstanceOf(BatteryDelivery::class, $batteryDeliveries[0]);
        $this->assertEquals('deliveryId', $batteryDeliveries[0]->id);
        $this->assertEquals('deliveryPassword', $batteryDeliveries[0]->password);
        $this->assertEquals(1, $batteryDeliveries[0]->order);
    }

    public function testDenormalizeDuplicatedDeliveries(): void
    {
        $deliveries = [
            [
                'id' => 'deliveryId',
                'password' => 'deliveryPassword',
                'order' => 1,
            ],
            [
                'id' => 'duplicatedDeliveryId',
                'password' => 'duplicatedDeliveryPassword',
                'order' => 2,
            ],
            [
                'id' => 'duplicatedDeliveryId',
                'password' => 'duplicatedDeliveryPassword',
                'order' => 2,
            ],
        ];

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Battery must contain unique deliveries. Duplicated deliveries: "duplicatedDeliveryId"');

        $batteryDeliveries = $this->subject->denormalize($deliveries);

        $this->assertCount(3, $batteryDeliveries);
    }
}
