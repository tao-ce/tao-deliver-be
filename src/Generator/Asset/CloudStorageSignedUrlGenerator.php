<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Generator\Asset;

use DateTime;
use Google\Cloud\Storage\Bucket;

class CloudStorageSignedUrlGenerator implements SignedUrlGeneratorInterface
{
    public const NAME = 'storage';

    public const FE_SERVICE_ID = 'cloud-storage';

    public const SIGNING_VERSION = 'v4';

    /** @var string */
    private $prefix;

    /** @var Bucket  */
    private $bucket;

    /** @var int */
    private $ttl;

    public function __construct(Bucket $bucket, $assetTtl, $prefix)
    {
        $this->bucket = $bucket;
        $this->ttl = $assetTtl;
        $this->prefix = $prefix;
    }

    public function generateUploadUrl(?string $path = null, ?int $ttl = null): string
    {
        $object = $this->bucket->object($this->prefix . DIRECTORY_SEPARATOR . $path);

        return $object->signedUrl(
            new DateTime(sprintf('%s sec', $ttl ?? $this->ttl)),
            [
                'method' => $this->getUploadMethod(),
                'version' => self::SIGNING_VERSION,
            ],
        );
    }

    public function generateDownloadUrl(string $path, ?string $url = null, array $queryParameters = [], ?int $ttl = null): string
    {
        $object = $this->bucket->object($this->prefix . DIRECTORY_SEPARATOR . $path);

        return $object->signedUrl(
            new DateTime(sprintf('%s sec', $ttl ?? $this->ttl)),
            $queryParameters,
        );
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
        return 'PUT';
    }
}
