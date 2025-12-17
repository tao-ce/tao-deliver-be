<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Battery\Model;

use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDelivery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BatteryTest extends TestCase
{
    private Battery $subject;
    private BatteryDelivery|MockObject $delivery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->delivery = $this->createDeliveryMock('pass', 1);

        $this->subject = new Battery(
            'id',
            'tenantId',
            'name',
            'description',
            deliveries: [$this->delivery],
        );
    }

    public function testConstructorDefaultValues(): void
    {
        $battery = new Battery(
            'id',
            'tenantId',
            'name',
        );

        $this->assertEmpty($battery->description);
        $this->assertEmpty($battery->deliveries);
        $this->assertEquals(Battery::STATUS_ACTIVE, $battery->status);
        $this->assertEquals(Battery::MODE_RANDOM_DELIVERY, $battery->mode);
    }

    public function testItCanRetrieveTheId(): void
    {
        $this->assertSame('id', $this->subject->getId());
    }

    public function testItCanRetrieveTheStatus(): void
    {
        $this->assertSame(Battery::STATUS_ACTIVE, $this->subject->status);
    }

    public function testItCanRetrieveTheTenantId(): void
    {
        $this->assertSame('tenantId', $this->subject->tenantId);
    }


    public function testItCanRetrieveTheName(): void
    {
        $this->assertSame('name', $this->subject->name);
    }

    public function testItCanRetrieveTheDescription(): void
    {
        $this->assertSame('description', $this->subject->description);
    }

    public function testItCanRetrieveTheDeliveries(): void
    {
        $this->assertSame([$this->delivery], array_values($this->subject->deliveries));
    }

    public function testItCanRetrieveTheMode(): void
    {
        $this->assertSame(Battery::MODE_RANDOM_DELIVERY, $this->subject->mode);
    }

    public function testGetDelivery(): void
    {
        $this->assertEquals($this->delivery, $this->subject->getDelivery('deliveryId'));
    }

    public function testHasDelivery(): void
    {
        $this->assertTrue($this->subject->hasDelivery($this->delivery));
        $this->assertFalse($this->subject->hasDelivery($this->createDeliveryMock(id: 'unknown')));
    }

    public function testGetDeliveryWithoutAllFields(): void
    {
        $delivery = $this->createDeliveryMock();

        $battery = new Battery(
            'id',
            'tenantId',
            'name',
            deliveries: [$delivery],
        );

        $batteryDelivery = $battery->getDelivery('deliveryId');

        $this->assertEquals($delivery, $batteryDelivery);
        $this->assertNull($batteryDelivery->password);
        $this->assertNull($batteryDelivery->order);
    }

    public function testGetPreviousDelivery(): void
    {
        $delivery1 = $this->createDeliveryMock(order: 1, id: 'delivery1');
        $delivery2 = $this->createDeliveryMock(order: 2, id: 'delivery2');
        $delivery3 = $this->createDeliveryMock(order: 3, id: 'delivery3');

        $battery = new Battery(
            'id',
            'tenantId',
            'name',
            mode: Battery::MODE_ALL_IN_SEQUENCE,
            deliveries: [$delivery1, $delivery2, $delivery3],
        );

        $this->assertEquals($delivery2, $battery->getPreviousDelivery('delivery3'));
        $this->assertEquals($delivery1, $battery->getPreviousDelivery('delivery2'));
        $this->assertNull($battery->getPreviousDelivery('delivery1'));
        $this->assertNull($battery->getPreviousDelivery('unknown'));
    }

    private function createDeliveryMock(
        ?string $password = null,
        ?int $order = null,
        string $id = 'deliveryId',
    ): BatteryDelivery {
        return $this
            ->getMockBuilder(BatteryDelivery::class)
            ->enableOriginalConstructor()
            ->setConstructorArgs([$id, $password, $order])
            ->getMock();
    }
}
