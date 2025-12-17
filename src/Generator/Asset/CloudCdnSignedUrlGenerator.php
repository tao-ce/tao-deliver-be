<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Generator\Asset;

use App\Cache\CacheTrait;
use Carbon\Carbon;
use Symfony\Contracts\Cache\CacheInterface;

class CloudCdnSignedUrlGenerator implements SignedUrlGeneratorInterface
{
    use CacheTrait;

    private const CACHE_URL_PATTERN = 'cached_signed_url-%s-%s';
    public const NAME = 'cdn';
    public const FE_SERVICE_ID = 'cloud-cdn';

    public function __construct(
        private readonly int $assetTtl,
        private readonly string $cloudCdnUrl,
        private readonly string $cloudCdnKeyName,
        private readonly string $cloudCdnKey,
        private readonly int $signedUrlCacheTtlShift,
        CacheInterface $cacheChain,
    ) {
        $this->cache = $cacheChain;
    }

    public function generateDownloadUrl(
        string $path,
        ?string $url = null,
        array $queryParameters = [],
        ?int $ttl = null,
    ): string {
        if (null === $url) {
            $url = $this->cloudCdnUrl;
        }
        $decodedKey = $this->base64UrlDecode($this->cloudCdnKey);
        $key = rawurlencode(sprintf(
            self::CACHE_URL_PATTERN,
            $url,
            md5($decodedKey . $path),
        ));

        $cached = $this->getFromCache($key);
        if ($cached !== null) {
            return $cached;
        }

        [$evaluatedTtl, $ttlForCache] = $this->evaluateTtl($ttl);

        $splitAssetPath = explode('/', $path);
        if (isset($queryParameters['prefix'])) {
            array_unshift($splitAssetPath, $queryParameters['prefix']);
        }
        $path = implode('/', array_map('rawurlencode', $splitAssetPath));

        $separator = (!str_contains($url, '?')) ? '?' : '&';
        $expirationTime = Carbon::now()->addSeconds($evaluatedTtl)->getTimestamp();

        $url = $this->getBaseUrl($url, $path)
            . "{$separator}Expires={$expirationTime}&KeyName={$this->cloudCdnKeyName}";

        $signature = hash_hmac('sha1', $url, $decodedKey, true);
        $encodedSignature = $this->base64UrlEncode($signature);

        $url .= "&Signature={$encodedSignature}";

        $this->setInCache($key, $url, $ttlForCache);

        return $url;
    }

    public function generateUploadUrl(?string $path = null): string
    {
        throw new UrlGeneratorException('Direct upload to CDN is not supported');
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getFeServiceId(): string
    {
        return self::FE_SERVICE_ID;
    }

    public function getUploadMethod(): ?string
    {
        return null;
    }

    private function base64UrlDecode($input): string
    {
        $input .= str_repeat('=', (4 - strlen($input) % 4) % 4);
        return base64_decode(strtr($input, '-_', '+/'), true);
    }

    private function base64UrlEncode($input): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($input));
    }

    private function getBaseUrl($url, $path): string
    {
        if ($path[0] !== DIRECTORY_SEPARATOR && substr($url, -1) !== DIRECTORY_SEPARATOR) {
            return $url . DIRECTORY_SEPARATOR . $path;
        }

        if ($path[0] === DIRECTORY_SEPARATOR && substr($url, -1) === DIRECTORY_SEPARATOR) {
            return rtrim($url, '/') . $path;
        }

        return $url . $path;
    }

    /**
     * @return int[] - [evaluatedTtl, ttlForCache]
     */
    private function evaluateTtl(?int $ttl = null): array
    {
        $evaluatedTtl = $ttl ?? $this->assetTtl;
        $ttlForCache = $evaluatedTtl > $this->signedUrlCacheTtlShift
            ? $evaluatedTtl - $this->signedUrlCacheTtlShift
            : $evaluatedTtl;

        return [$evaluatedTtl, $ttlForCache];
    }
}
