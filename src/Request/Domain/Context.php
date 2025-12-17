<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Request\Domain;

class Context
{
    public function __construct(
        private bool $isReview = false,
        private ?string $tenantId = null,
        private ?string $deliveryId = null,
        private ?string $userId = null,
        private ?string $batteryId = null,
    ) {
    }

    public function withReview(): static
    {
        $that = clone $this;
        $that->isReview = true;

        return $that;
    }

    public function withTenantId(string $tenantId): static
    {
        $that = clone $this;
        $that->tenantId = $tenantId;

        return $that;
    }

    public function withDeliveryId(?string $deliveryId = null): static
    {
        $that = clone $this;
        $that->deliveryId = $deliveryId;

        return $that;
    }

    public function withUserId(string $userId): static
    {
        $that = clone $this;
        $that->userId = $userId;

        return $that;
    }

    public function withBatteryId(?string $batteryId = null): static
    {
        $that = clone $this;
        $that->batteryId = $batteryId;

        return $that;
    }

    public function isReview(): bool
    {
        return $this->isReview;
    }

    public function toArray(): array
    {
        return array_filter(
            [
                'tenantId' => $this->tenantId,
                'deliveryId' => $this->deliveryId,
                'userId' => $this->userId,
                'batteryId' => $this->batteryId,
            ],
        );
    }

    public function fits(self $context): array
    {
        $currentContext = match (true) {
            $context->isReview && $context->deliveryId !== null => $this->withBatteryId(),
            $this->batteryId !== null => $this->withDeliveryId(),
            default => $this,
        };

        return array_diff_assoc(
            $currentContext->toArray(),
            $context->toArray(),
        );
    }
}
