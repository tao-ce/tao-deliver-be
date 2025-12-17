<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Driver;

use Carbon\Carbon;
use JsonException;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentDriverException;
use OAT\Bundle\ElasticsearchDocumentManagerBundle\Driver\ElasticsearchDocumentDriver;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Decorator class for ElasticsearchDocumentDriver to cache the results.
 *
 * @see ElasticsearchDocumentDriver
 */
class CachedElasticsearchDocumentDriver implements DocumentDriverInterface
{
    private const ITEM_CACHE_KEY_PREFIX = 'document_manager_cache_item_';
    private const COLLECTION_CACHE_KEY_PREFIX = 'document_manager_cache_collection_';
    private const COLLECTION_CACHE_TAG_PREFIX = 'document_manager_tag_collection_';

    public function __construct(
        private readonly ElasticsearchDocumentDriver $driver,
        private readonly LoggerInterface $logger,
        private readonly TagAwareCacheInterface $cache,
        private readonly array $cachableStorages = [],
        private readonly int $ttl = 24 * 60 * 60, // 24 hours
    ) {
    }

    public static function getName(): string
    {
        return 'cached-elasticsearch';
    }

    public function applyOptions(array $options = []): DocumentDriverInterface
    {
        return $this;
    }

    /**
     * @throws DocumentDriverException
     * @throws InvalidArgumentException
     */
    public function getDocumentData(string $documentStorageName, string $documentId): ?DocumentDriverDataInterface
    {
        return $this->isCacheEnabled($documentStorageName)
            ? $this->cache->get(
                $this->getCacheKey($documentStorageName, $documentId),
                function (
                    ItemInterface $item,
                    bool &$save,
                ) use (
                    $documentStorageName,
                    $documentId
                ): ?DocumentDriverDataInterface {
                    $documentDriverData = $this->driver->getDocumentData($documentStorageName, $documentId);

                    if (!$documentDriverData instanceof DocumentDriverDataInterface) {
                        $save = false;
                        return null;
                    }
                    $this->logger->info(sprintf('Saving document data in cache: %s', $documentId));
                    $item->expiresAt(Carbon::now()->addSeconds($this->ttl)->toDateTime());

                    $save = $save && !($documentDriverData->getData()['isDeleted'] ?? false);

                    return $documentDriverData;
                },
            )
            : $this->driver->getDocumentData($documentStorageName, $documentId);
    }

    /**
     * @throws DocumentDriverException
     */
    public function saveDocumentData(string $documentStorageName, DocumentDriverDataInterface $documentData): void
    {
        if ($this->isCacheEnabled($documentStorageName)) {
            try {
                $this->cache->delete($this->getCacheKey($documentStorageName, $documentData->getId()));
                $this->cache->invalidateTags([$this->getCollectionCacheTag($documentStorageName)]);
            } catch (InvalidArgumentException $exception) {
                $this->logger->error(
                    sprintf(
                        'Failed to invalidate cache for document %s. Reason: %s',
                        $documentData->getId(),
                        $exception->getMessage(),
                    ),
                    compact('exception'),
                );
            }
        }

        $this->driver->saveDocumentData($documentStorageName, $documentData);
    }

    /**
     * @throws DocumentDriverException
     */
    public function deleteDocumentData(string $documentStorageName, DocumentDriverDataInterface $documentData): void
    {
        if ($this->isCacheEnabled($documentStorageName)) {
            try {
                $this->cache->delete($this->getCacheKey($documentStorageName, $documentData->getId()));
                $this->cache->invalidateTags([$this->getCollectionCacheTag($documentStorageName)]);
            } catch (InvalidArgumentException $exception) {
                $this->logger->error(
                    sprintf(
                        'Failed to invalidate cache for document %s. Reason: %s',
                        $documentData->getId(),
                        $exception->getMessage(),
                    ),
                    compact('exception'),
                );
            }
        }

        $this->driver->deleteDocumentData($documentStorageName, $documentData);
    }

    /**
     * @throws DocumentDriverException
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    public function getDocumentsCollectionData(string $documentStorageName, array $criteria = [], ?int $limit = null, ?int $offset = null): iterable
    {
        return $this->isCacheEnabled($documentStorageName)
            ? $this->cache->get(
                $this->getCollectionCacheKey($documentStorageName, $criteria),
                function (
                    ItemInterface $item,
                    bool &$save,
                ) use (
                    $documentStorageName,
                    $criteria,
                    $limit,
                    $offset
                ): array {
                    $data = iterator_to_array(
                        $this->driver->getDocumentsCollectionData(
                            $documentStorageName,
                            $criteria,
                            $limit,
                            $offset,
                        ),
                    );

                    if (!$data) {
                        $save = false;
                        return [];
                    }

                    $this->logger->info(
                        sprintf('Saving documents data in cache: %s', json_encode($criteria, JSON_THROW_ON_ERROR)),
                    );
                    $item->tag($this->getCollectionCacheTag($documentStorageName));
                    $item->expiresAt(Carbon::now()->addSeconds($this->ttl)->toDateTime());

                    return $data;
                },
            )
            : $this->driver->getDocumentsCollectionData($documentStorageName, $criteria, $limit, $offset);
    }

    /**
     * @throws DocumentDriverException
     */
    public function saveDocumentsCollectionData(string $documentStorageName, iterable $documentsCollectionData): void
    {
        if ($this->isCacheEnabled($documentStorageName)) {
            /**
             * @var DocumentDriverDataInterface $documentData
             */
            foreach ($documentsCollectionData as $documentData) {
                try {
                    $this->cache->delete($this->getCacheKey($documentStorageName, $documentData->getId()));
                } catch (InvalidArgumentException $exception) {
                    $this->logger->error(
                        sprintf(
                            'Failed to invalidate cache for document %s. Reason: %s',
                            $documentData->getId(),
                            $exception->getMessage(),
                        ),
                        compact('exception'),
                    );

                    continue;
                }
            }

            try {
                $this->cache->invalidateTags([$this->getCollectionCacheTag($documentStorageName)]);
            } catch (InvalidArgumentException $exception) {
                $this->logger->error(
                    sprintf(
                        'Failed to invalidate cache. Reason: %s',
                        $exception->getMessage(),
                    ),
                    compact('exception'),
                );
            }
        }

        $this->driver->saveDocumentsCollectionData($documentStorageName, $documentsCollectionData);
    }

    /**
     * @throws DocumentDriverException
     */
    public function deleteDocumentsCollectionData(string $documentStorageName, iterable $documentsCollectionData): void
    {
        if ($this->isCacheEnabled($documentStorageName)) {
            /**
             * @var DocumentDriverDataInterface $documentData
             */
            foreach ($documentsCollectionData as $documentData) {
                try {
                    $this->cache->delete($this->getCacheKey($documentStorageName, $documentData->getId()));
                } catch (InvalidArgumentException $exception) {
                    $this->logger->error(
                        sprintf(
                            'Failed to invalidate cache for document %s. Reason: %s',
                            $documentData->getId(),
                            $exception->getMessage(),
                        ),
                        compact('exception'),
                    );

                    continue;
                }
            }

            try {
                $this->cache->invalidateTags([$this->getCollectionCacheTag($documentStorageName)]);
            } catch (InvalidArgumentException $exception) {
                $this->logger->error(
                    sprintf(
                        'Failed to invalidate cache. Reason: %s',
                        $exception->getMessage(),
                    ),
                    compact('exception'),
                );
            }
        }

        $this->driver->deleteDocumentsCollectionData($documentStorageName, $documentsCollectionData);
    }

    private function isCacheEnabled(string $documentStorageName): bool
    {
        $res = in_array($documentStorageName, $this->cachableStorages, true);
        $this->logger->info('Cache is enabled for: ' . implode(', ', $this->cachableStorages));
        $this->logger->info(sprintf('Checking if cache is enabled for %s: %s', $documentStorageName, $res ? 'yes' : 'no'));
        return $res;
    }

    private function getCacheKey(string $documentStorageName, string $documentId): string
    {
        return sprintf('%s%s_%s', self::ITEM_CACHE_KEY_PREFIX, $documentStorageName, $documentId);
    }

    /**
     * @throws JsonException
     */
    private function getCollectionCacheKey(string $documentStorageName, array $criteria): string
    {
        return sprintf('%s%s_%s', self::COLLECTION_CACHE_KEY_PREFIX, $documentStorageName, md5(json_encode($criteria, JSON_THROW_ON_ERROR)));
    }

    private function getCollectionCacheTag(string $documentStorageName): string
    {
        return sprintf('%s%s', self::COLLECTION_CACHE_TAG_PREFIX, $documentStorageName);
    }
}
