<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DynamicQueryApi\Serializer\Denormalizer;

use App\DynamicQueryApi\Model\Battery;
use App\DynamicQueryApi\Serializer\Denormalizer\BatteryNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BatteryNormalizerTest extends TestCase
{
    private BatteryNormalizer $subject;

    protected function setUp(): void
    {
        $this->subject = new BatteryNormalizer();
    }

    public function testSupportsNormalization(): void
    {
        $this->assertTrue($this->subject->supportsNormalization($this->getTestBattery()));
    }

    public function testNormalize(): void
    {
        $this->assertSame([
            '_id' => 'id',
            'name' => 'name',
            'description' => 'description',
            'mode' => 'mode',
            'status' => 'status',
            'tenantId' => 'tenantId',
            'deliveryIds' => ['deliveryId1', 'deliveryId2'],
        ], $this->subject->normalize($this->getTestBattery()));
    }

    public function testNormalizeForLtiDeepLinking(): void
    {
        $this->assertSame([
            'id' => 'id',
            'name' => 'name',
            'nrOfDeliveries' => 2,
        ], $this->subject->normalize($this->getTestBattery(), null, [BatteryNormalizer::CONTEXT_VIEW => BatteryNormalizer::VIEW_LTI_DEEP_LINKING]));
    }

    public function testSupportsDenormalization(): void
    {
        $this->assertTrue($this->subject->supportsDenormalization('data', Battery::class));
    }

    public function testDenormalize(): void
    {
        $battery = $this->subject->denormalize($this->getNormalizedData(), Battery::class);

        $this->assertSame('id', $battery->getId());
        $this->assertSame('name', $battery->getName());
        $this->assertSame('description', $battery->getDescription());
        $this->assertSame('mode', $battery->getMode());
        $this->assertSame('status', $battery->getStatus());
        $this->assertSame('tenantId', $battery->getTenantId());
        $this->assertSame(['deliveryId1', 'deliveryId2'], $battery->getDeliveryIds());
    }

    public function testDenormalizeIfDataIsNotArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot denormalize data into App\DynamicQueryApi\Model\Battery: data is not an array');

        $this->subject->denormalize('foo', Battery::class);
    }

    /**
     * @dataProvider arrayKeyProvider
     */
    public function testDenormalizeIfMandatoryArrayKeyIsMissing(string $key): void
    {
        $normalizedData = $this->getNormalizedData();

        unset($normalizedData[$key]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Cannot denormalize data into App\DynamicQueryApi\Model\Battery: the following mandatory keys are missing: %s',
            $key,
        ));

        $this->subject->denormalize($normalizedData, Battery::class);
    }

    public function arrayKeyProvider(): array
    {
        return [
            ['_id'],
            ['name'],
            ['description'],
            ['mode'],
            ['status'],
            ['tenantId'],
            ['deliveries'],
        ];
    }

    private function getNormalizedData(): array
    {
        return [
            '_id' => 'id',
            'name' => 'name',
            'description' => 'description',
            'mode' => 'mode',
            'status' => 'status',
            'tenantId' => 'tenantId',
            'deliveries' => [
                ['id' => 'deliveryId1'],
                ['id' => 'deliveryId2'],
            ],
        ];
    }

    private function getTestBattery(): Battery
    {
        return new Battery(
            'id',
            'name',
            'description',
            'mode',
            'status',
            'tenantId',
            ['deliveryId1', 'deliveryId2'],
        );
    }
}
