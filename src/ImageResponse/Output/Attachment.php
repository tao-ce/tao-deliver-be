<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\ImageResponse\Output;

use DateTimeInterface;
use JsonSerializable;

final readonly class Attachment implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $responseId,
        public DateTimeInterface $createdAt,
        public int $pageNumber,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'responseId' => $this->responseId,
            'createdAt' => $this->createdAt->format(DateTimeInterface::ATOM),
            'pageNumber' => $this->pageNumber,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
