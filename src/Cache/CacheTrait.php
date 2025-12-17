<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Cache;

use App\Domain\Delivery\Model\Delivery;
use App\Traits\FilesystemTrait;
use Psr\Cache\CacheException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

trait CacheTrait
{
    use FilesystemTrait;

    protected CacheInterface $cache;

    /**
     * @throws CacheException
     */
    private function getFromCache(string $key)
    {
        return $this->cache->get(
            $key,
            static function (ItemInterface $item, bool &$save) {
                $save = false;

                return null;
            },
        );
    }

    /**
     * @throws CacheException
     */
    private function setInCache(string $key, $value, ?int $ttl = null): bool
    {
        $this->cache->get(
            $key,
            static function (ItemInterface $item) use ($value, $ttl) {
                if ($ttl) {
                    $item->expiresAfter($ttl);
                }

                return $value;
            },
            INF,
        );

        return true;
    }

    private function getCacheKey(
        string $deliveryId,
        ?string $itemIdentifier,
        string $fileName,
        ?string $locale = null,
    ): string {
        return md5(
            $this->buildPathFor(
                $deliveryId,
                $itemIdentifier,
                $locale
                    ? $this->buildPathFor(Delivery::LOCALE_FOLDER_NAME, $locale)
                    : null,
                $fileName,
            ),
        );
    }
}
