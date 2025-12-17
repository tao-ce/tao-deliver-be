<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Filter;

use App\DocumentManager\Driver\CachedElasticsearchDocumentDriver;
use App\DocumentManager\Filter\CollectionEnrollmentFilterFactory;
use InvalidArgumentException;
use OAT\Bundle\BigtableDocumentManagerBundle\Driver\BigtableDocumentDriver;
use OAT\Bundle\DocumentManagerBundle\Filter\DocumentCollectionFilterInterface;
use OAT\Bundle\ElasticsearchDocumentManagerBundle\Driver\ElasticsearchDocumentDriver;
use PHPUnit\Framework\TestCase;

class CollectionEnrollmentFilterFactoryTest extends TestCase
{
    private CollectionEnrollmentFilterFactory $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new CollectionEnrollmentFilterFactory();
    }

    public function testCreateForFindSessionByDeliveryExecutionIdForElasticsearchDriver(): void
    {
        $filter = $this->subject->createForFindSessionByDeliveryExecutionId(
            $this->createMock(ElasticsearchDocumentDriver::class),
            'deliveryExecutionId',
        );

        $this->assertInstanceOf(DocumentCollectionFilterInterface::class, $filter);
        $this->assertEquals(
            [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['deliveryExecutionId' => 'deliveryExecutionId']],
                        ],
                    ],
                ],
            ],
            $filter->getFilter(),
        );
    }

    public function testCreateForFindSessionByDeliveryExecutionIdForCachedElasticsearchDriver(): void
    {
        $filter = $this->subject->createForFindSessionByDeliveryExecutionId(
            $this->createMock(CachedElasticsearchDocumentDriver::class),
            'deliveryExecutionId',
        );

        $this->assertInstanceOf(DocumentCollectionFilterInterface::class, $filter);
        $this->assertEquals(
            [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['deliveryExecutionId' => 'deliveryExecutionId']],
                        ],
                    ],
                ],
            ],
            $filter->getFilter(),
        );
    }

    public function testCreateForFindSessionByDeliveryExecutionIdFailsForUnsupportedDriver(): void
    {
        $driver = $this->createMock(BigtableDocumentDriver::class);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Driver %s is not supported by method %s of filter %s',
                $driver::class,
                'createForFindSessionByDeliveryExecutionId',
                CollectionEnrollmentFilterFactory::class,
            ),
        );

        $this->subject->createForFindSessionByDeliveryExecutionId(
            $driver,
            'deliveryExecutionId',
        );
    }
}
