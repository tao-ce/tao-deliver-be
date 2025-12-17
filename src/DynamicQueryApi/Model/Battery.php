<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DynamicQueryApi\Model;

class Battery
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $description,
        private readonly string $mode,
        private readonly string $status,
        private readonly string $tenantId,
        private readonly array $deliveryIds,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    /**
     * @return string[]
     */
    public function getDeliveryIds(): array
    {
        return $this->deliveryIds;
    }
}
