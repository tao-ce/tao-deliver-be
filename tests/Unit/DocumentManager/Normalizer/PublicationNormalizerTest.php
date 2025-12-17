<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Normalizer;

use App\DocumentManager\Normalizer\PublicationNormalizer;
use App\Domain\Publication\Model\Publication;
use App\Tests\Traits\DomainTestingTrait;
use Exception;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;
use OAT\Bundle\DocumentManagerBundle\Tests\Resources\Traits\DocumentTestingTrait;
use PHPUnit\Framework\TestCase;

class PublicationNormalizerTest extends TestCase
{
    use DocumentTestingTrait;
    use DomainTestingTrait;

    /** @var PublicationNormalizer */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new PublicationNormalizer();
    }

    public function testNormalizationSupport(): void
    {
        $driverMock = $this->createMock(DocumentDriverInterface::class);

        $this->assertTrue($this->subject->supports($driverMock, Publication::class));
        $this->assertFalse($this->subject->supports($driverMock, 'invalidClass'));
    }

    public function testDenormalizationSuccess(): void
    {
        $publication = $this->createTestPublication();
        $publication->clearUpdates();

        $driverData = $this->createTestDocumentDriverData('id', [
            'tenantId' => $publication->getTenantId(),
            'packagePath' => $publication->getPackagePath(),
            'packageRef' => $publication->getPackageRef(),
            'packageConfiguration' => $publication->getPackageConfiguration(),
            'reports' => $publication->getReports(),
            'status' => $publication->getStatus(),
            'deliveryId' => $publication->getDeliveryId(),
            'locale' => $publication->getLocale(),
        ]);

        $this->assertEquals($publication, $this->subject->denormalizeDocument($driverData, Publication::class));
    }

    public function testDenormalizationFailure(): void
    {
        $this->expectException(DocumentNormalizerException::class);
        $this->expectExceptionMessage('Cannot denormalize publication');

        $this->subject->denormalizeDocument($this->createTestDocumentDriverData('id'), Publication::class);
    }

    public function testNormalizationSuccess(): void
    {
        $publication = $this->createTestPublication();

        $documentDriverData = $this->subject->normalizeDocument($publication);
        $data = $documentDriverData->getData();

        $this->assertInstanceOf(DocumentDriverDataInterface::class, $documentDriverData);
        $this->assertEquals($publication->getId(), $documentDriverData->getId());
        $this->assertEquals($publication->getTenantId(), $data['tenantId']);
        $this->assertEquals($publication->getPackagePath(), $data['packagePath']);
        $this->assertEquals($publication->getPackageConfiguration(), $data['packageConfiguration']);
        $this->assertEquals($publication->getReports(), $data['reports']);
        $this->assertEquals($publication->getStatus(), $data['status']);
        $this->assertEquals($publication->getDeliveryId(), $data['deliveryId']);
        $this->assertEquals($publication->getLocale(), $data['locale']);
    }

    public function testNormalizationFailure(): void
    {
        $this->expectException(DocumentNormalizerException::class);
        $this->expectExceptionMessage('Cannot normalize publication');

        $document = $this->createMock(Publication::class);
        $document->method('getId')->willThrowException(new Exception());
        $this->subject->normalizeDocument($document);
    }
}
