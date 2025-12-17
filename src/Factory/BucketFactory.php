<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Factory;

use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;

class BucketFactory
{
    /** @var StorageClient */
    private $storageClient;

    public function __construct(StorageClient $storageClient)
    {
        $this->storageClient = $storageClient;
    }

    public function create(string $bucketName): Bucket
    {
        return $this->storageClient->bucket($bucketName);
    }
}
