<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Normalizer;

use App\DocumentManager\Normalizer\DeliveryNormalizer;
use App\Domain\Delivery\Model\Delivery;
use App\Tests\Traits\DomainTestingTrait;
use Carbon\Carbon;
use Exception;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;
use OAT\Bundle\DocumentManagerBundle\Tests\Resources\Traits\DocumentTestingTrait;
use PHPUnit\Framework\TestCase;

class DeliveryNormalizerTest extends TestCase
{
    use DocumentTestingTrait;
    use DomainTestingTrait;

    /** @var DeliveryNormalizer */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2020-01-01'));

        $this->subject = new DeliveryNormalizer();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Carbon::setTestNow();
    }

    public function testNormalizationSupport(): void
    {
        $driverMock = $this->createMock(DocumentDriverInterface::class);

        $this->assertTrue($this->subject->supports($driverMock, Delivery::class));
        $this->assertFalse($this->subject->supports($driverMock, 'invalidClass'));
    }

    public function testDenormalizationSuccess(): void
    {
        $delivery = $this->createTestDelivery();
        $delivery->clearUpdates();

        $driverData = $this->createTestDocumentDriverData('id', [
            'tenantId' => $delivery->getTenantId(),
            'createdAt' => $delivery->getCreatedAt()->getTimestamp(),
            'compactTestFilePath' => $delivery->getQtiCompactTestFilePath(),
            'configuration' => $delivery->getConfiguration(),
            'qtiItemsMapping' => $delivery->getQtiItemsMapping(),
            'packageRef' => $delivery->getPackageRef(),
            'isDeleted' => $delivery->isDeleted(),
            'draftId' => $delivery->getDraftId(),
        ]);

        $this->assertEquals($delivery, $this->subject->denormalizeDocument($driverData, Delivery::class));
    }

    public function testDenormalizationOfOutdatedFormatIsSuccessful(): void
    {
        $delivery = $this->createTestDelivery();
        $delivery->clearUpdates();

        $driverData = $this->createTestDocumentDriverData('id', [
            'tenantId' => $delivery->getTenantId(),
            'createdAt' => $delivery->getCreatedAt()->getTimestamp(),
            'compactTestFilePath' => $delivery->getQtiCompactTestFilePath(),
            'configuration' => $delivery->getConfiguration(),
            'qtiItemsMapping' => $delivery->getQtiItemsMapping(),
            'packageRef' => $delivery->getPackageRef(),
            'isDeleted' => $delivery->isDeleted(),
            'draftId' => $delivery->getDraftId(),
        ]);

        $this->assertEquals($delivery, $this->subject->denormalizeDocument($driverData, Delivery::class));
    }

    public function testDenormalizationIfIsDeletedAndDraftIdAreNotDefined(): void
    {
        $delivery = $this->createTestDelivery();
        $delivery->clearUpdates();

        $driverData = $this->createTestDocumentDriverData('id', [
            'tenantId' => $delivery->getTenantId(),
            'createdAt' => $delivery->getCreatedAt()->getTimestamp(),
            'compactTestFilePath' => $delivery->getQtiCompactTestFilePath(),
            'configuration' => $delivery->getConfiguration(),
            'qtiItemsMapping' => $delivery->getQtiItemsMapping(),
            'packageRef' => $delivery->getPackageRef(),
        ]);

        $denormalizedDelivery = $this->subject->denormalizeDocument($driverData, Delivery::class);

        $this->assertFalse($denormalizedDelivery->isDeleted());
        $this->assertNull($denormalizedDelivery->getDraftId());
    }

    public function testDenormalizationFailure(): void
    {
        $this->expectException(DocumentNormalizerException::class);
        $this->expectExceptionMessage('Cannot denormalize delivery');

        $this->subject->denormalizeDocument($this->createTestDocumentDriverData('id'), Delivery::class);
    }

    public function testNormalizationSuccess(): void
    {
        $delivery = $this->createTestDelivery();

        $documentDriverData = $this->subject->normalizeDocument($delivery);
        $data = $documentDriverData->getData();

        $this->assertInstanceOf(DocumentDriverDataInterface::class, $documentDriverData);
        $this->assertEquals($delivery->getId(), $documentDriverData->getId());
        $this->assertEquals($delivery->getTenantId(), $data['tenantId']);
        $this->assertEquals($delivery->getQtiCompactTestFilePath(), $data['compactTestFilePath']);
        $this->assertEquals($delivery->getConfiguration(), $data['configuration']);
        $this->assertSame($delivery->getPackageRef(), $data['packageRef']);
        $this->assertFalse($delivery->isDeleted());
        $this->assertNull($delivery->getDraftId());
    }

    public function testNormalizationFailure(): void
    {
        $this->expectException(DocumentNormalizerException::class);
        $this->expectExceptionMessage('Cannot normalize delivery');

        $document = $this->createMock(Delivery::class);
        $document->method('getId')->willThrowException(new Exception());
        $this->subject->normalizeDocument($document);
    }
}
