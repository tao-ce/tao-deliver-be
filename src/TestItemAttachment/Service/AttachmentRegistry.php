<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestItemAttachment\Service;

use App\ContentServiceApi\Gateway\ContentServiceApiGateway;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

readonly class AttachmentRegistry
{
    private const CACHE_KEY_PREFIX = 'attachments';

    public function __construct(
        private LoggerInterface $logger,
        private ContentServiceApiGateway $client,
        private CacheInterface $cacheChain,
        private string $contentServiceCdnKeyName,
        private int $assetTtl,
        private int $signedUrlCacheTtlShift,
    ) {
    }

    public function resolveAttachments(string $tenantId, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $ids = array_unique($ids);
        sort($ids);

        return $this->cacheChain->get(
            $this->createCacheKey($ids),
            function (CacheItemInterface $item, bool &$save) use ($tenantId, $ids): array {
                $item->expiresAfter($this->assetTtl - $this->signedUrlCacheTtlShift);
                $uploadedFiles = [];
                try {
                    $uploadedFiles = $this->client->getUploadedFiles($tenantId, $ids, $this->assetTtl * 1000);
                } catch (TransportExceptionInterface $exception) {
                    $this->logger->critical(
                        "Failed to fetch item attachments: {$exception->getMessage()}",
                        compact('exception'),
                    );
                    $save = false;
                }
                $result = [];
                foreach ($uploadedFiles as $uploadedFile) {
                    if ($save) {
                        parse_str(parse_url($uploadedFile['publicUrl'], PHP_URL_QUERY) ?: '', $query);
                        if (empty($query['KeyName']) || $query['KeyName'] !== $this->contentServiceCdnKeyName) {
                            $save = false;
                        }
                    }
                    $result[$uploadedFile['asset']['id']] = [
                        'id' => $uploadedFile['asset']['id'],
                        'url' => $uploadedFile['publicUrl'],
                        'name' => basename($uploadedFile['asset']['virtualPath'] ?? 'attachment'),
                        'type' => $uploadedFile['asset']['type'] ?? 'application/octet-stream',
                    ];
                }
                return $result;
            },
        );
    }

    private function createCacheKey(array $ids): string
    {
        return sprintf(
            '%s-%s-%s',
            self::CACHE_KEY_PREFIX,
            $this->contentServiceCdnKeyName,
            hash('sha256', implode('_', $ids)),
        );
    }
}
