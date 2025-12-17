<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Filter;

use App\DocumentManager\Driver\CachedElasticsearchDocumentDriver;
use InvalidArgumentException;
use OAT\Bundle\DocumentManagerBundle\Driver\ArrayDocumentDriver;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Filter\DocumentCollectionFilter;
use OAT\Bundle\DocumentManagerBundle\Filter\DocumentCollectionFilterInterface;
use OAT\Bundle\ElasticsearchDocumentManagerBundle\Driver\ElasticsearchDocumentDriver;

class CollectionEnrollmentFilterFactory
{
    public function __construct(
    ) {
    }

    public function createForFindSessionByDeliveryExecutionId(
        DocumentDriverInterface $driver,
        string $deliveryExecutionId,
    ): DocumentCollectionFilterInterface {
        switch (true) {
            case $driver instanceof ArrayDocumentDriver:
                $filter = [
                    'deliveryExecutionId' => $deliveryExecutionId,
                ];
                break;
            case $driver instanceof CachedElasticsearchDocumentDriver:
            case $driver instanceof ElasticsearchDocumentDriver:
                $filter = [
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['term' => ['deliveryExecutionId' => $deliveryExecutionId]],
                            ],
                        ],
                    ],
                ];
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
}
