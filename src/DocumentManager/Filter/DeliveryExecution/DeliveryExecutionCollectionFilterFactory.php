<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Filter\DeliveryExecution;

use App\DocumentManager\Driver\CachedElasticsearchDocumentDriver;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionStatus;
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

    public function createForUserIdAndStatuses(
        DocumentDriverInterface $driver,
        string $userId,
        array $deliveryExecutionStatuses,
    ): DocumentCollectionFilterInterface {
        if (empty($deliveryExecutionStatuses)) {
            throw new InvalidArgumentException('deliveryExecutionStatuses cannot be empty');
        }

        $deliveryExecutionStatuses = array_map(
            static fn(DeliveryExecutionStatus|string $status): string => $status instanceof DeliveryExecutionStatus
                ? $status->value
                : $status,
            $deliveryExecutionStatuses,
        );

        switch (true) {
            case $driver instanceof ArrayDocumentDriver:
                $filter = [
                    'userId' => $userId,
                    'status' => $deliveryExecutionStatuses,
                ];
                break;
            case $driver instanceof CachedElasticsearchDocumentDriver:
            case $driver instanceof ElasticsearchDocumentDriver:
                $filter = [
                    'query' => [
                        'bool' => [
                            'filter' => [
                                ['term' => ['ltiLaunchParameters.user_id' => $userId]],
                                ['terms' => ['status' => $deliveryExecutionStatuses]],
                            ],
                        ],
                    ],
                ];
                break;
            case $driver instanceof BigtableDocumentDriver:
                if (count($deliveryExecutionStatuses) === 1) {
                    $onlyStatus = (string)reset($deliveryExecutionStatuses);
                    $statusValueOrFilter = Filter::value()->regex(
                        '^' . preg_quote($onlyStatus, '/') . '$',
                    );
                } else {
                    $statusValueOrFilter = Filter::interleave();
                    foreach ($deliveryExecutionStatuses as $status) {
                        $statusValueOrFilter->addFilter(
                            Filter::value()->regex('^' . preg_quote($status, '/') . '$'),
                        );
                    }
                }

                $userIdChain = Filter::chain()
                    ->addFilter(Filter::qualifier()->exactMatch('userId'))
                    ->addFilter(Filter::value()->regex('^' . preg_quote($userId, '/') . '$'));

                $statusChain = Filter::chain()
                    ->addFilter(Filter::qualifier()->exactMatch('status'))
                    ->addFilter($statusValueOrFilter);

                $qualifierAndChains = Filter::chain()
                    ->addFilter($userIdChain)
                    ->addFilter($statusChain);

                $filter = [
                    'filter' => $qualifierAndChains,
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
