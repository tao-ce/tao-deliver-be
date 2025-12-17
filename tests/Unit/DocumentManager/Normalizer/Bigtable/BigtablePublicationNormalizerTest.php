<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Normalizer\Bigtable;

use App\DocumentManager\Normalizer\Bigtable\BigtablePublicationNormalizer;
use App\DocumentManager\Normalizer\PublicationNormalizer;
use App\Domain\Publication\Model\Publication;
use App\Tests\Traits\DomainTestingTrait;
use Exception;
use OAT\Bundle\BigtableDocumentManagerBundle\Driver\BigtableDocumentDriver;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;
use OAT\Bundle\DocumentManagerBundle\Tests\Resources\Traits\DocumentTestingTrait;
use PHPUnit\Framework\TestCase;

class BigtablePublicationNormalizerTest extends TestCase
{
    use DocumentTestingTrait;
    use DomainTestingTrait;

    /** @var PublicationNormalizer */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new BigtablePublicationNormalizer();
    }

    public function testNormalizationSupport(): void
    {
        $driverMock = $this->createMock(BigtableDocumentDriver::class);

        $this->assertTrue($this->subject->supports($driverMock, Publication::class));
        $this->assertFalse($this->subject->supports($driverMock, 'invalidClass'));
        $this->assertFalse($this->subject->supports($this->createMock(DocumentDriverInterface::class), Publication::class));
    }

    public function testDenormalizationSuccess(): void
    {
        $publication = $this->createTestPublication();
        $publication->clearUpdates();

        $driverData = $this->createTestDocumentDriverData('id', [
            'data' => [
                'tenantId' => [
                    [
                        'label' => '',
                        'value' => $publication->getTenantId(),
                        'timestamp' => '12315321',
                    ],
                ],
                'packagePath' => [
                    [
                        'label' => '',
                        'value' => $publication->getPackagePath(),
                        'timestamp' => '12315321',
                    ],
                ],
                'packageRef' => [
                    [
                        'label' => '',
                        'value' => $publication->getPackageRef(),
                        'timestamp' => '12315321',
                    ],
                ],
                'packageConfiguration' => [
                    [
                        'label' => '',
                        'value' => json_encode($publication->getPackageConfiguration()),
                        'timestamp' => '12315321',
                    ],
                ],
                'reports' => [
                    [
                        'label' => '',
                        'value' => json_encode($publication->getReports()),
                        'timestamp' => '12315321',
                    ],
                ],
                'status' => [
                    [
                        'label' => '',
                        'value' => $publication->getStatus(),
                        'timestamp' => '12315321',
                    ],
                ],
                'deliveryId' => [
                    [
                        'label' => '',
                        'value' => $publication->getDeliveryId(),
                        'timestamp' => '12315321',
                    ],
                ],
                'locale' => [
                    [
                        'label' => '',
                        'value' => $publication->getLocale(),
                        'timestamp' => '12315321',
                    ],
                ],
            ],
        ]);

        $this->assertEquals($publication, $this->subject->denormalizeDocument($driverData, Publication::class));
    }

    public function testDenormalizationFailure(): void
    {
        $this->expectException(DocumentNormalizerException::class);
        $this->expectExceptionMessage('Cannot denormalize publication with id: "id" with errorMessage: Undefined array key "data"');

        $this->subject->denormalizeDocument($this->createTestDocumentDriverData('id'), Publication::class);
    }

    public function testNormalizationSuccess(): void
    {
        $publication = $this->createTestPublication();

        $documentDriverData = $this->subject->normalizeDocument($publication);
        $data = $documentDriverData->getData()['data'];

        $this->assertInstanceOf(DocumentDriverDataInterface::class, $documentDriverData);
        $this->assertEquals($publication->getId(), $documentDriverData->getId());
        $this->assertEquals($publication->getTenantId(), $data['tenantId']);
        $this->assertEquals($publication->getPackagePath(), $data['packagePath']);
        $this->assertEquals($publication->getPackageConfiguration(), json_decode($data['packageConfiguration'], true));
        $this->assertEquals($publication->getReports(), json_decode($data['reports'], true));
        $this->assertEquals($publication->getStatus(), $data['status']);
        $this->assertEquals($publication->getDeliveryId(), $data['deliveryId']);
        $this->assertEquals($publication->getLocale(), $data['locale']);
    }

    public function testNormalizationFailure(): void
    {
        $this->expectException(DocumentNormalizerException::class);
        $this->expectExceptionMessage('Cannot normalize publication');

        $document = $this->createMock(Publication::class);
        $document->method('getUpdates')->willThrowException(new Exception('error'));
        $this->subject->normalizeDocument($document);
    }
}
