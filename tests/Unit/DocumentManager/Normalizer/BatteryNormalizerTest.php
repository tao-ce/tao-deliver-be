<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Normalizer;

use App\DocumentManager\Normalizer\BatteryDeliveriesNormalizer;
use App\DocumentManager\Normalizer\BatteryNormalizer;
use App\Domain\Battery\Model\Battery;
use App\Tests\Traits\DomainTestingTrait;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;
use OAT\Bundle\DocumentManagerBundle\Tests\Resources\Traits\DocumentTestingTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BatteryNormalizerTest extends TestCase
{
    use DocumentTestingTrait;
    use DomainTestingTrait;

    private BatteryDeliveriesNormalizer|MockObject $batteryDeliveriesNormalizer;
    private BatteryNormalizer $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->batteryDeliveriesNormalizer = $this->createMock(BatteryDeliveriesNormalizer::class);
        $this->subject = new BatteryNormalizer($this->batteryDeliveriesNormalizer);
    }

    public function testNormalizationSupport(): void
    {
        $driverMock = $this->createMock(DocumentDriverInterface::class);

        $this->assertTrue($this->subject->supports($driverMock, Battery::class));
        $this->assertFalse($this->subject->supports($driverMock, 'invalidClass'));
    }

    public function testDenormalizeDocumentSuccess(): void
    {
        $battery = $this->createTestBattery();
        $battery->clearUpdates();

        $driverData = $this->createTestDocumentDriverData(
            'batteryId',
            json_decode(json_encode($battery), true),
        );

        $this->batteryDeliveriesNormalizer
            ->expects($this->once())
            ->method('denormalize')
            ->with($driverData->getData()['deliveries'])
            ->willReturn($battery->deliveries);

        $this->assertEquals($battery, $this->subject->denormalizeDocument($driverData, Battery::class));
    }

    public function testDenormalizeDocumentFailure(): void
    {
        $this->expectException(DocumentNormalizerException::class);
        $this->expectExceptionMessage('Cannot denormalize battery');

        $this->subject->denormalizeDocument($this->createTestDocumentDriverData('id'), Battery::class);
    }

    public function testNormalizeDocumentSuccess(): void
    {
        $battery = $this->createTestBattery();

        $documentDriverData = $this->subject->normalizeDocument($battery);
        $data = $documentDriverData->getData();

        $this->assertInstanceOf(DocumentDriverDataInterface::class, $documentDriverData);
        $this->assertEquals($battery->getId(), $documentDriverData->getId());
        $this->assertEquals($battery->tenantId, $data['tenantId']);
        $this->assertEquals($battery->name, $data['name']);
        $this->assertEquals($battery->description, $data['description']);
        $this->assertEquals($battery->status, $data['status']);
        $this->assertEquals($battery->mode, $data['mode']);

        foreach ($battery->deliveries as $delivery) {
            $deliveryData = current($data['deliveries']);

            $this->assertEquals($delivery->id, $deliveryData['id']);
            $this->assertEquals($delivery->password, $deliveryData['password']);
            $this->assertEquals($delivery->order, $deliveryData['order']);
        }
    }
}
