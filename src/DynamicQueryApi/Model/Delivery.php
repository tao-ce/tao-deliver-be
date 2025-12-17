<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DynamicQueryApi\Model;

use DateTimeInterface;

class Delivery
{
    public function __construct(
        private readonly string $id,
        private readonly array $qtiItemsMapping,
        private readonly string $tenantId,
        private readonly string $compactTestFilePath,
        private readonly array $configuration,
        private readonly DateTimeInterface $createdAt,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getQtiItemsMapping(): array
    {
        return $this->qtiItemsMapping;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getCompactTestFilePath(): string
    {
        return $this->compactTestFilePath;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }
}
