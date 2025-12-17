<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Filter\DeliveryExecution;

use App\DocumentManager\Filter\DeliveryExecution\DeliveryExecutionCollectionFilterFactory;
use Google\Cloud\Bigtable\Filter;
use InvalidArgumentException;
use OAT\Bundle\BigtableDocumentManagerBundle\Driver\BigtableDocumentDriver;
use OAT\Bundle\DocumentManagerBundle\Driver\ArrayDocumentDriver;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Filter\DocumentCollectionFilterInterface;
use OAT\Bundle\ElasticsearchDocumentManagerBundle\Driver\ElasticsearchDocumentDriver;
use PHPUnit\Framework\TestCase;

class DeliveryExecutionCollectionFilterFactoryTest extends TestCase
{
    /** @var DeliveryExecutionCollectionFilterFactory */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new DeliveryExecutionCollectionFilterFactory();
    }

    public function testCreateForFindByDeliveryIdForArrayDriver(): void
    {
        $filter = $this->subject->createForFindByDeliveryId(
            $this->createMock(ArrayDocumentDriver::class),
            'deliveryId',
        );

        $this->assertInstanceOf(DocumentCollectionFilterInterface::class, $filter);
        $this->assertEquals(
            [
                'deliveryId' => 'deliveryId',
            ],
            $filter->getFilter(),
        );
    }

    public function testCreateForFindByDeliveryIdForElasticsearchDriver(): void
    {
        $filter = $this->subject->createForFindByDeliveryId(
            $this->createMock(ElasticsearchDocumentDriver::class),
            'deliveryId',
        );

        $this->assertInstanceOf(DocumentCollectionFilterInterface::class, $filter);
        $this->assertEquals(
            [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['deliveryId.keyword' => 'deliveryId']],
                        ],
                    ],
                ],
            ],
            $filter->getFilter(),
        );
    }

    public function testCreateForFindByDeliveryIdForBigtableDriver(): void
    {
        $filter = $this->subject->createForFindByDeliveryId(
            $this->createMock(BigtableDocumentDriver::class),
            'deliveryId',
        );

        $deliveryIdConditionFilter = Filter::key()->regex('[^\#]+\#deliveryId\#\C*');

        $this->assertEquals(['filter' => $deliveryIdConditionFilter], $filter->getFilter());
    }

    public function testCreateForFindByDeliveryIdFailsForUnsupportedDriver(): void
    {
        $driver = $this->createMock(DocumentDriverInterface::class);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Driver %s is not supported by method %s of filter %s',
            $driver::class,
            'createForFindByDeliveryId',
            DeliveryExecutionCollectionFilterFactory::class,
        ));

        $this->subject->createForFindByDeliveryId(
            $driver,
            'deliveryId',
        );
    }
}
