<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Filter;

use App\DocumentManager\Driver\CachedElasticsearchDocumentDriver;
use App\Domain\Delivery\Model\Delivery;
use InvalidArgumentException;
use OAT\Bundle\DocumentManagerBundle\Driver\ArrayDocumentDriver;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Filter\DocumentCollectionFilter;
use OAT\Bundle\DocumentManagerBundle\Filter\DocumentCollectionFilterInterface;
use OAT\Bundle\ElasticsearchDocumentManagerBundle\Driver\ElasticsearchDocumentDriver;

class CollectionTenantIdFilterFactory
{
    public function __construct(
    ) {
    }

    public function createForFindByTenantId(
        DocumentDriverInterface $driver,
        string $tenantId,
        string $documentClass,
    ): DocumentCollectionFilterInterface {
        switch (true) {
            case $driver instanceof ArrayDocumentDriver:
                $filter = [
                    'tenantId' => $tenantId,
                ];
                break;
            case $driver instanceof CachedElasticsearchDocumentDriver:
            case $driver instanceof ElasticsearchDocumentDriver:
                $filter = [
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['term' => ['tenantId' => $tenantId]],
                            ],
                        ],
                    ],
                ];

                if ($documentClass === Delivery::class) {
                    $filter['query']['bool']['must_not'][] = ['term' => ['isDeleted' => true]];
                }

                break;
            default:
                throw new InvalidArgumentException(
                    sprintf(
                        'Driver %s is not supported by method %s of filter %s',
                        $driver::class,
                        __FUNCTION__,
                        __CLASS__,
                    ),
                );
        }

        return new DocumentCollectionFilter($filter);
    }

    public function createForFindByTenantIdAndIds(
        string $driverName,
        string $tenantId,
        array $ids,
    ): DocumentCollectionFilterInterface {
        $filter = match ($driverName) {
            CachedElasticsearchDocumentDriver::getName(), ElasticsearchDocumentDriver::getName() => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['tenantId' => $tenantId]],
                            ['terms' => ['_id' => $ids]],
                        ],
                    ],
                ],
            ],
            default => throw new InvalidArgumentException(
                sprintf('Driver %s is not supported by method %s of filter %s', $driverName, __FUNCTION__, __CLASS__),
            ),
        };

        return new DocumentCollectionFilter($filter);
    }

    /**
     * @param array<TermsFilterEntity> $terms
     */
    public function createForFindByTenantIdAndTerms(
        string $driverName,
        string $tenantId,
        array $terms,
    ): DocumentCollectionFilterInterface {
        $filter = match ($driverName) {
            CachedElasticsearchDocumentDriver::getName(), ElasticsearchDocumentDriver::getName() => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['tenantId' => $tenantId]],
                            ...array_map(static fn(TermsFilterEntity $term) => [
                                'terms' => [$term->field => $term->values],
                            ], $terms),
                        ],
                    ],
                ],
            ],
            default => throw new InvalidArgumentException(
                sprintf('Driver %s is not supported by method %s of filter %s', $driverName, __FUNCTION__, __CLASS__),
            ),
        };

        return new DocumentCollectionFilter($filter);
    }
}
