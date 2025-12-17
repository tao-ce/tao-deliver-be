<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData;

use InvalidArgumentException;
use JsonSerializable;

readonly class OverallComment implements JsonSerializable
{
    public function __construct(public int $timestamp, public string $content)
    {
    }

    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp,
            'content' => $this->content,
        ];
    }

    public static function fromArray(array $overallCommentDto): static
    {
        return new static(
            $overallCommentDto['timestamp'] ?? throw new InvalidArgumentException(
                'Invalid overall comment structure `timestamp` not provided',
            ),
            $overallCommentDto['content'] ?? throw new InvalidArgumentException(
                'Invalid overall comment structure `content` not provided',
            ),
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
