<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Filter\DeliveryExecution;

use App\DocumentManager\Filter\CollectionTenantIdFilterFactory;
use App\Domain\Delivery\Model\Delivery;
use InvalidArgumentException;
use OAT\Bundle\BigtableDocumentManagerBundle\Driver\BigtableDocumentDriver;
use OAT\Bundle\DocumentManagerBundle\Driver\ArrayDocumentDriver;
use OAT\Bundle\ElasticsearchDocumentManagerBundle\Driver\ElasticsearchDocumentDriver;
use PHPUnit\Framework\TestCase;

class DeliveryCollectionFilterFactoryTest extends TestCase
{
    private CollectionTenantIdFilterFactory $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new CollectionTenantIdFilterFactory();
    }

    public function testCreateForFindByTenantIdForArrayDriver(): void
    {
        $filter = $this->subject->createForFindByTenantId(
            $this->createMock(ArrayDocumentDriver::class),
            'tenantId',
            'documentClass',
        );

        $this->assertEquals(
            [
                'tenantId' => 'tenantId',
            ],
            $filter->getFilter(),
        );
    }

    public function testCreateForFindByTenantIdForElasticsearchDriver(): void
    {
        $filter = $this->subject->createForFindByTenantId(
            $this->createMock(ElasticsearchDocumentDriver::class),
            'tenantId',
            'documentClass',
        );

        $this->assertEquals(
            [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['tenantId' => 'tenantId']],
                        ],
                    ],
                ],
            ],
            $filter->getFilter(),
        );
    }

    public function testCreateForFindByTenantIdFailsForUnsupportedDriver(): void
    {
        $driver = $this->createMock(BigtableDocumentDriver::class);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Driver %s is not supported by method %s of filter %s',
            $driver::class,
            'createForFindByTenantId',
            CollectionTenantIdFilterFactory::class,
        ));

        $this->subject->createForFindByTenantId(
            $driver,
            'tenantId',
            'documentClass',
        );
    }

    public function testCreateForFindByTenantIdWithDeliveryDocumentClass(): void
    {
        $filter = $this->subject->createForFindByTenantId(
            $this->createMock(ElasticsearchDocumentDriver::class),
            'tenantId',
            Delivery::class,
        );

        $this->assertSame(
            [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['tenantId' => 'tenantId']],
                        ],
                        'must_not' => [
                            ['term' => ['isDeleted' => true]],
                        ],
                    ],
                ],
            ],
            $filter->getFilter(),
        );
    }
}
