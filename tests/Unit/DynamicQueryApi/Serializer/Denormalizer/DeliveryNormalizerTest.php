<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DynamicQueryApi\Serializer\Denormalizer;

use App\DynamicQueryApi\Model\Delivery;
use App\DynamicQueryApi\Serializer\Denormalizer\DeliveryNormalizer;
use Carbon\Carbon;
use DateTimeInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DeliveryNormalizerTest extends TestCase
{
    private DeliveryNormalizer $subject;
    private DateTimeInterface $testDate;

    protected function setUp(): void
    {
        $this->subject = new DeliveryNormalizer();
        $this->testDate = Carbon::now();
    }

    public function testSupportsNormalization(): void
    {
        $this->assertTrue($this->subject->supportsNormalization($this->getTestDelivery()));
    }

    public function testNormalize(): void
    {
        $this->assertEquals([
            '_id' => 'id',
            'qtiItemsMapping' => ['qtiItemsMapping'],
            'tenantId' => 'tenantId',
            'compactTestFilePath' => 'compactTestFilePath',
            'configuration' => ['label' => 'Delivery Label'],
            'createdAt' => $this->testDate->toDateTime(),
        ], $this->subject->normalize($this->getTestDelivery()));
    }

    public function testNormalizeForLtiDeepLinking(): void
    {
        $this->assertSame([
            'id' => 'id',
            'name' => 'Delivery Label',
        ], $this->subject->normalize($this->getTestDelivery(), null, [DeliveryNormalizer::CONTEXT_VIEW => DeliveryNormalizer::VIEW_LTI_DEEP_LINKING]));
    }

    public function testSupportsDenormalization(): void
    {
        $this->assertTrue($this->subject->supportsDenormalization('data', Delivery::class));
    }

    public function testDenormalize(): void
    {
        $delivery = $this->subject->denormalize($this->getNormalizedData(), Delivery::class);

        $this->assertSame('id', $delivery->getId());
        // TODO
    }

    public function testDenormalizeIfDataIsNotArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot denormalize data into App\DynamicQueryApi\Model\Delivery: data is not an array');

        $this->subject->denormalize('foo', Delivery::class);
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
            'Cannot denormalize data into App\DynamicQueryApi\Model\Delivery: the following mandatory keys are missing: %s',
            $key,
        ));

        $this->subject->denormalize($normalizedData, Delivery::class);
    }

    public function arrayKeyProvider(): array
    {
        return [
            ['_id'],
            ['qtiItemsMapping'],
            ['tenantId'],
            ['compactTestFilePath'],
            ['configuration'],
            ['createdAt'],
        ];
    }

    private function getNormalizedData(): array
    {
        return [
            '_id' => 'id',
            'qtiItemsMapping' => ['foo' => 'bar'],
            'tenantId' => 'tenantId',
            'compactTestFilePath' => 'compactTestFilePath',
            'configuration' => ['label' => 'Delivery Label'],
            'createdAt' => 1654387200000,
        ];
    }

    private function getTestDelivery(): Delivery
    {
        return new Delivery(
            'id',
            ['qtiItemsMapping'],
            'tenantId',
            'compactTestFilePath',
            ['label' => 'Delivery Label'],
            $this->testDate->toDateTime(),
        );
    }
}
