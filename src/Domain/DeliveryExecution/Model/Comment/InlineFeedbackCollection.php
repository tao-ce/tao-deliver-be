<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\Comment;

class InlineFeedbackCollection
{
    private const GROUPED_FEEDBACK_KEY = 'feedbackOwners';

    public function __construct(private array $feedbacks = [])
    {
    }

    public function toArray(): array
    {
        return $this->feedbacks;
    }

    public function addFeedback(string $itemId, array $feedback): void
    {
        unset($this->feedbacks[$itemId][self::GROUPED_FEEDBACK_KEY]);
        $this->feedbacks[$itemId] = $feedback;
    }

    public function addOwnerFeedback(string $owner, string $itemId, array $feedback): void
    {
        if (!isset($this->feedbacks[$itemId][self::GROUPED_FEEDBACK_KEY])) {
            $this->removeFeedback($itemId);
        }
        $this->feedbacks[$itemId][self::GROUPED_FEEDBACK_KEY][$owner] = $feedback;
    }

    public function removeFeedback(string $itemId): void
    {
        unset($this->feedbacks[$itemId]);
    }

    public function getFeedback(string $itemId): array
    {
        return array_merge_recursive(...array_values($this->feedbacks[$itemId][self::GROUPED_FEEDBACK_KEY] ?? [[]]))
            ?: $this->feedbacks[$itemId]
            ?? [];
    }

    public function getOwnerFeedback(string $owner, string $itemId): array
    {
        $legacyFeedbacks = $this->feedbacks[$itemId] ?? [];
        unset($legacyFeedbacks[self::GROUPED_FEEDBACK_KEY]);
        return array_merge_recursive(
            $this->feedbacks[$itemId][self::GROUPED_FEEDBACK_KEY][$owner] ?? [],
            $legacyFeedbacks,
        );
    }
}
