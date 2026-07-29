<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA ;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DocumentManager\Filter\DeliveryExecution;

use App\DocumentManager\Driver\CachedElasticsearchDocumentDriver;
use App\DocumentManager\Filter\DeliveryExecution\DeliveryExecutionCollectionFilterFactory;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use Google\Cloud\Bigtable\Filter;
use InvalidArgumentException;
use OAT\Bundle\BigtableDocumentManagerBundle\Driver\BigtableDocumentDriver;
use OAT\Bundle\DocumentManagerBundle\Driver\ArrayDocumentDriver;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Filter\DocumentCollectionFilterInterface;
use OAT\Bundle\ElasticsearchDocumentManagerBundle\Driver\ElasticsearchDocumentDriver;
use PHPUnit\Framework\TestCase;

final class DeliveryExecutionCollectionFilterFactoryCreateForUserIdAndStatusesTest extends TestCase
{
    private DeliveryExecutionCollectionFilterFactory $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new DeliveryExecutionCollectionFilterFactory();
    }

    public function testItThrowsWhenStatusesAreEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('deliveryExecutionStatuses cannot be empty');

        $this->subject->createForUserIdAndStatuses(
            $this->createMock(ArrayDocumentDriver::class),
            'user-1',
            [],
        );
    }

    public function testItCreatesFilterForArrayDriver(): void
    {
        $filter = $this->subject->createForUserIdAndStatuses(
            $this->createMock(ArrayDocumentDriver::class),
            'user-1',
            [DeliveryExecution::STATUS_INITIAL, DeliveryExecution::STATUS_INTERACTING],
        );

        $this->assertInstanceOf(DocumentCollectionFilterInterface::class, $filter);
        $this->assertSame(
            [
                'userId' => 'user-1',
                'status' => [DeliveryExecution::STATUS_INITIAL, DeliveryExecution::STATUS_INTERACTING],
            ],
            $filter->getFilter(),
        );
    }

    public function testItCreatesFilterForElasticsearchDriver(): void
    {
        $filter = $this->subject->createForUserIdAndStatuses(
            $this->createMock(ElasticsearchDocumentDriver::class),
            'user-1',
            [DeliveryExecution::STATUS_INITIAL, DeliveryExecution::STATUS_INTERACTING],
        );

        $this->assertSame(
            [
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['ltiLaunchParameters.user_id' => 'user-1']],
                            ['terms' => ['status' => [DeliveryExecution::STATUS_INITIAL, DeliveryExecution::STATUS_INTERACTING]]],
                        ],
                    ],
                ],
            ],
            $filter->getFilter(),
        );
    }

    public function testItCreatesFilterForCachedElasticsearchDriver(): void
    {
        $filter = $this->subject->createForUserIdAndStatuses(
            $this->createMock(CachedElasticsearchDocumentDriver::class),
            'user-1',
            [DeliveryExecution::STATUS_INITIAL, DeliveryExecution::STATUS_INTERACTING],
        );

        $this->assertSame(
            [
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['ltiLaunchParameters.user_id' => 'user-1']],
                            ['terms' => ['status' => [DeliveryExecution::STATUS_INITIAL, DeliveryExecution::STATUS_INTERACTING]]],
                        ],
                    ],
                ],
            ],
            $filter->getFilter(),
        );
    }

    public function testItCreatesFilterForBigtableDriver(): void
    {
        $filter = $this->subject->createForUserIdAndStatuses(
            $this->createMock(BigtableDocumentDriver::class),
            'user-1',
            [DeliveryExecution::STATUS_INTERACTING, DeliveryExecution::STATUS_TERMINATED],
        );

        $expected = Filter::chain()
            ->addFilter(
                Filter::chain()
                    ->addFilter(Filter::qualifier()->exactMatch('userId'))
                    ->addFilter(Filter::value()->regex('^' . preg_quote('user-1', '/') . '$')),
            )
            ->addFilter(
                Filter::chain()
                    ->addFilter(Filter::qualifier()->exactMatch('status'))
                    ->addFilter(
                        Filter::interleave()
                            ->addFilter(Filter::value()->regex('^' . preg_quote(DeliveryExecution::STATUS_INTERACTING, '/') . '$'))
                            ->addFilter(Filter::value()->regex('^' . preg_quote(DeliveryExecution::STATUS_TERMINATED, '/') . '$')),
                    ),
            );

        $this->assertEquals(['filter' => $expected], $filter->getFilter());
    }

    public function testItCreatesFilterForBigtableDriverWithSingleStatus(): void
    {
        $filter = $this->subject->createForUserIdAndStatuses(
            $this->createMock(BigtableDocumentDriver::class),
            'user-1',
            [DeliveryExecution::STATUS_CLOSED],
        );

        $expected = Filter::chain()
            ->addFilter(
                Filter::chain()
                    ->addFilter(Filter::qualifier()->exactMatch('userId'))
                    ->addFilter(Filter::value()->regex('^' . preg_quote('user-1', '/') . '$')),
            )
            ->addFilter(
                Filter::chain()
                    ->addFilter(Filter::qualifier()->exactMatch('status'))
                    ->addFilter(
                        Filter::value()->regex(
                            '^' . preg_quote(DeliveryExecution::STATUS_CLOSED, '/') . '$',
                        ),
                    ),
            );

        $this->assertEquals(['filter' => $expected], $filter->getFilter());
    }

    public function testItThrowsForUnsupportedDriver(): void
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
            'user-1',
            [DeliveryExecution::STATUS_TERMINATED],
        );
    }
}
