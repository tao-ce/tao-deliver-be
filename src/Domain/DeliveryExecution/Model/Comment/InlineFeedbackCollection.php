<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\Comment;

class InlineFeedbackCollection
{
    public function __construct(private array $comments = [])
    {
    }

    public function toArray(): array
    {
        return $this->comments;
    }

    public function addFeedback(string $itemId, mixed $feedback): self
    {
        if (empty($feedback)) {
            unset($this->comments[$itemId]);
        } else {
            $this->comments[$itemId] = $feedback;
        }

        return $this;
    }

    public function getFeedback(string $itemId): array
    {
        return $this->comments[$itemId] ?? [];
    }
}
