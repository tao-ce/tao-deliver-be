<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model;

use Carbon\Carbon;
use DateTimeInterface;

final readonly class Invalidation
{
    public function __construct(
        private string $invalidatedBy,
        private DateTimeInterface $invalidatedAt,
        private bool $isResultInvalidated = true,
    ) {
    }

    public function getInvalidatedBy(): string
    {
        return $this->invalidatedBy;
    }

    public function getInvalidatedAt(): DateTimeInterface
    {
        return $this->invalidatedAt;
    }

    public function isResultInvalidated(): bool
    {
        return $this->isResultInvalidated;
    }

    public function toArray(): array
    {
        return [
            'invalidatedBy' => $this->invalidatedBy,
            'invalidatedAt' => $this->invalidatedAt->getTimestamp(),
            'isResultInvalidated' => $this->isResultInvalidated,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['invalidatedBy'],
            Carbon::createFromTimestamp($data['invalidatedAt']),
            $data['isResultInvalidated'] ?? true,
        );
    }

    public static function create(string $userLogin): self
    {
        return new self(
            $userLogin,
            Carbon::now(),
            true,
        );
    }
}
