<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Traits;

use App\Cache\CacheTrait;
use App\Tests\Helpers\ContainerAwareTestingHelper;
use Symfony\Contracts\Cache\CacheInterface;

trait CacheTestingTrait
{
    use CacheTrait;

    protected function setUp(): void
    {
        $this->setUpTestCache();
    }

    protected function setUpTestCache(): void
    {
        ContainerAwareTestingHelper::checkKernelTestCase(static::class);

        $this->cache = static::getContainer()->get(CacheInterface::class);
    }

    protected function populateCache(string $key, $value, ?int $ttl = null): bool
    {
        return $this->setInCache($key, $value, $ttl);
    }

    protected function assertCacheKeyExist(string $cacheKey): void
    {
        $this->assertNotNull($this->getFromCache($cacheKey));
    }

    protected function assertCacheKeyDoesNotExist(string $cacheKey): void
    {
        $this->assertNull($this->getFromCache($cacheKey));
    }

    protected function assertCacheKeyContains(string $cacheKey, $expectedContent): void
    {
        $this->assertEquals($expectedContent, $this->getFromCache($cacheKey));
    }
}
