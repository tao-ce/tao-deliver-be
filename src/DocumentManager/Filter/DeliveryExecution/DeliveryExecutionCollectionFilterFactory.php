<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Filter\DeliveryExecution;

use App\DocumentManager\Driver\CachedElasticsearchDocumentDriver;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use Google\Cloud\Bigtable\Filter;
use InvalidArgumentException;
use OAT\Bundle\BigtableDocumentManagerBundle\Driver\BigtableDocumentDriver;
use OAT\Bundle\DocumentManagerBundle\Driver\ArrayDocumentDriver;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Filter\DocumentCollectionFilter;
use OAT\Bundle\DocumentManagerBundle\Filter\DocumentCollectionFilterInterface;
use OAT\Bundle\ElasticsearchDocumentManagerBundle\Driver\ElasticsearchDocumentDriver;

class DeliveryExecutionCollectionFilterFactory
{
    public function createForFindByDeliveryId(
        DocumentDriverInterface $driver,
        string $deliveryId,
    ): DocumentCollectionFilterInterface {
        switch (true) {
            case $driver instanceof ArrayDocumentDriver:
                $filter = [
                    'deliveryId' => $deliveryId,
                ];
                break;
            case $driver instanceof CachedElasticsearchDocumentDriver:
            case $driver instanceof ElasticsearchDocumentDriver:
                $filter = [
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['term' => ['deliveryId.keyword' => $deliveryId]],
                            ],
                        ],
                    ],
                ];
                break;
            case $driver instanceof BigtableDocumentDriver:
                /** @noinspection PregQuoteUsageInspection */
                $delimiter = preg_quote(DeliveryExecution::DOCUMENT_KEY_DELIMITER);

                $filter = [
                    'filter' => Filter::key()->regex(
                        "[^{$delimiter}]+{$delimiter}{$deliveryId}{$delimiter}\C*",
                    ),
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
