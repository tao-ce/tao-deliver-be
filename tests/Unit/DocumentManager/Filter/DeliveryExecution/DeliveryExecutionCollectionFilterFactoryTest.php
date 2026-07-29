<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Filter\DeliveryExecution;

use App\DocumentManager\Driver\CachedElasticsearchDocumentDriver;
use App\DocumentManager\Filter\DeliveryExecution\DeliveryExecutionCollectionFilterFactory;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionStatus;
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

    public function testCreateForFindByDeliveryIdForCachedElasticsearchDriver(): void
    {
        $filter = $this->subject->createForFindByDeliveryId(
            $this->createMock(CachedElasticsearchDocumentDriver::class),
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

        /** @noinspection PregQuoteUsageInspection */
        $delimiter = preg_quote(DeliveryExecution::DOCUMENT_KEY_DELIMITER);

        $deliveryIdConditionFilter = Filter::key()->regex(
            "[^{$delimiter}]+{$delimiter}deliveryId{$delimiter}\\C*",
        );

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

    public function testCreateForUserIdAndStatusesForArrayDriver(): void
    {
        $filter = $this->subject->createForUserIdAndStatuses(
            $this->createMock(ArrayDocumentDriver::class),
            'userId',
            ['initial', 'suspended'],
        );

        $this->assertInstanceOf(DocumentCollectionFilterInterface::class, $filter);
        $this->assertEquals(
            [
                'userId' => 'userId',
                'status' => ['initial', 'suspended'],
            ],
            $filter->getFilter(),
        );
    }

    public function testCreateForUserIdAndStatusesForElasticsearchDriver(): void
    {
        $filter = $this->subject->createForUserIdAndStatuses(
            $this->createMock(ElasticsearchDocumentDriver::class),
            'userId',
            ['initial', 'suspended'],
        );

        $this->assertInstanceOf(DocumentCollectionFilterInterface::class, $filter);
        $this->assertEquals(
            [
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['ltiLaunchParameters.user_id' => 'userId']],
                            ['terms' => ['status' => ['initial', 'suspended']]],
                        ],
                    ],
                ],
            ],
            $filter->getFilter(),
        );
    }

    public function testCreateForUserIdAndStatusesForCachedElasticsearchDriver(): void
    {
        $filter = $this->subject->createForUserIdAndStatuses(
            $this->createMock(CachedElasticsearchDocumentDriver::class),
            'userId',
            ['initial', 'suspended'],
        );

        $this->assertInstanceOf(DocumentCollectionFilterInterface::class, $filter);
        $this->assertEquals(
            [
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['ltiLaunchParameters.user_id' => 'userId']],
                            ['terms' => ['status' => ['initial', 'suspended']]],
                        ],
                    ],
                ],
            ],
            $filter->getFilter(),
        );
    }

    public function testCreateForUserIdAndStatusesForBigtableDriver(): void
    {
        $filter = $this->subject->createForUserIdAndStatuses(
            $this->createMock(BigtableDocumentDriver::class),
            'userId',
            ['initial', 'suspended'],
        );

        $statusValueOrFilter = Filter::interleave();
        $statusValueOrFilter->addFilter(Filter::value()->regex('^' . preg_quote('initial', '/') . '$'));
        $statusValueOrFilter->addFilter(Filter::value()->regex('^' . preg_quote('suspended', '/') . '$'));

        $userIdChain = Filter::chain()
            ->addFilter(Filter::qualifier()->exactMatch('userId'))
            ->addFilter(Filter::value()->regex('^' . preg_quote('userId', '/') . '$'));

        $statusChain = Filter::chain()
            ->addFilter(Filter::qualifier()->exactMatch('status'))
            ->addFilter($statusValueOrFilter);

        $qualifierAndChains = Filter::chain()
            ->addFilter($userIdChain)
            ->addFilter($statusChain);

        $this->assertInstanceOf(DocumentCollectionFilterInterface::class, $filter);
        $this->assertEquals(['filter' => $qualifierAndChains], $filter->getFilter());
    }

    public function testCreateForUserIdAndStatusesWithEnumStatuses(): void
    {
        $filter = $this->subject->createForUserIdAndStatuses(
            $this->createMock(ArrayDocumentDriver::class),
            'userId',
            [DeliveryExecutionStatus::STATUS_INTERACTING, DeliveryExecutionStatus::STATUS_SUSPENDED],
        );

        $this->assertInstanceOf(DocumentCollectionFilterInterface::class, $filter);
        $this->assertEquals(
            [
                'userId' => 'userId',
                'status' => ['interacting', 'suspended'],
            ],
            $filter->getFilter(),
        );
    }

    public function testCreateForUserIdAndStatusesWithEnumStatusesForElasticsearchDriver(): void
    {
        $filter = $this->subject->createForUserIdAndStatuses(
            $this->createMock(ElasticsearchDocumentDriver::class),
            'userId',
            [DeliveryExecutionStatus::STATUS_INTERACTING, DeliveryExecutionStatus::STATUS_SUSPENDED],
        );

        $this->assertInstanceOf(DocumentCollectionFilterInterface::class, $filter);
        $this->assertEquals(
            [
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['ltiLaunchParameters.user_id' => 'userId']],
                            ['terms' => ['status' => ['interacting', 'suspended']]],
                        ],
                    ],
                ],
            ],
            $filter->getFilter(),
        );
    }

    public function testCreateForUserIdAndStatusesWithMixedStatuses(): void
    {
        $filter = $this->subject->createForUserIdAndStatuses(
            $this->createMock(ArrayDocumentDriver::class),
            'userId',
            [DeliveryExecutionStatus::STATUS_INTERACTING, 'closed'],
        );

        $this->assertInstanceOf(DocumentCollectionFilterInterface::class, $filter);
        $this->assertEquals(
            [
                'userId' => 'userId',
                'status' => ['interacting', 'closed'],
            ],
            $filter->getFilter(),
        );
    }

    public function testCreateForUserIdAndStatusesFailsForEmptyStatuses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('deliveryExecutionStatuses cannot be empty');

        $this->subject->createForUserIdAndStatuses(
            $this->createMock(ArrayDocumentDriver::class),
            'userId',
            [],
        );
    }

    public function testCreateForUserIdAndStatusesFailsForUnsupportedDriver(): void
    {
        $driver = $this->createMock(DocumentDriverInterface::class);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Driver %s is not supported by method %s of filter %s',
            $driver::class,
            'createForUserIdAndStatuses',
            DeliveryExecutionCollectionFilterFactory::class,
        ));

        $this->subject->createForUserIdAndStatuses(
            $driver,
            'userId',
            ['active'],
        );
    }
}
