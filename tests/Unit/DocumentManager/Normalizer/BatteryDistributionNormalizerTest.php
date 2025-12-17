<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Normalizer;

use App\DocumentManager\Normalizer\BatteryDeliveriesNormalizer;
use App\DocumentManager\Normalizer\BatteryDistributionNormalizer;
use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDelivery;
use App\Domain\Battery\Model\BatteryDistribution;
use App\Tests\Traits\DomainTestingTrait;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;
use OAT\Bundle\DocumentManagerBundle\Tests\Resources\Traits\DocumentTestingTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BatteryDistributionNormalizerTest extends TestCase
{
    use DocumentTestingTrait;
    use DomainTestingTrait;

    private BatteryDeliveriesNormalizer|MockObject $batteryDeliveriesNormalizer;
    private BatteryDistributionNormalizer $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->batteryDeliveriesNormalizer = $this->createMock(BatteryDeliveriesNormalizer::class);
        $this->subject = new BatteryDistributionNormalizer($this->batteryDeliveriesNormalizer);
    }

    public function testNormalizationSupport(): void
    {
        $driverMock = $this->createMock(DocumentDriverInterface::class);

        $this->assertTrue($this->subject->supports($driverMock, BatteryDistribution::class));
        $this->assertFalse($this->subject->supports($driverMock, 'invalidClass'));
    }

    public function testDenormalizeDocumentSuccess(): void
    {
        $distribution = $this->createTestBatteryDistribution();

        $distribution->clearUpdates();
        $distribution->battery->clearUpdates();

        $driverData = $this->createTestDocumentDriverData(
            'dIresu#batteryId',
            [
                'userId' => $distribution->userId,
                'battery' => json_decode(json_encode($distribution->battery), true),
            ],
        );

        $this->batteryDeliveriesNormalizer
            ->expects($this->once())
            ->method('denormalize')
            ->with($driverData->getData()['battery']['deliveries'])
            ->willReturn($distribution->battery->deliveries);

        $this->assertEquals(
            $distribution,
            $this->subject->denormalizeDocument($driverData, BatteryDistribution::class),
        );
    }

    public function testDenormalizeDocumentFailure(): void
    {
        $this->expectException(DocumentNormalizerException::class);
        $this->expectExceptionMessage('Cannot denormalize battery distribution');

        $this->subject->denormalizeDocument(
            $this->createTestDocumentDriverData('dIresu#batteryId'),
            BatteryDistribution::class,
        );
    }

    public function testNormalizeDocumentSuccess(): void
    {
        $distribution = $this->createTestBatteryDistribution();

        $documentDriverData = $this->subject->normalizeDocument($distribution);
        $data = $documentDriverData->getData();

        $this->assertInstanceOf(DocumentDriverDataInterface::class, $documentDriverData);
        $this->assertEquals($distribution->getId(), $documentDriverData->getId());
        $this->assertEquals($distribution->userId, $data['userId']);

        /** @var Battery $battery */
        $battery = $data['battery'];

        $this->assertEquals($distribution->battery->getId(), $battery->getId());
        $this->assertEquals(
            [
                [
                    'id' => 'deliveryId',
                    'password' => null,
                    'order' => null,
                    'startDateValidation' => 1726759110,
                    'endDateValidation' => 1726759110,
                ],
            ],
            json_decode(json_encode(array_values($battery->deliveries)), true),
        );
    }

    public function testNormalizationFailure(): void
    {
        $this->expectException(DocumentNormalizerException::class);
        $this->expectExceptionMessage('Cannot normalize battery distribution');

        $this->subject->normalizeDocument($this->createMock(DocumentInterface::class));
    }
}
