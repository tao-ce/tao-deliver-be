<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

trait CommentsTrait
{
    private array $comments = [];

    public function getComments(): array
    {
        return $this->comments;
    }

    public function withItemComment(string $itemIdentifier, string $comment): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->comments[$itemIdentifier][] = $comment;

        return $deliveryExecutionExtraStateData;
    }

    public function getCommentsForItem(string $itemIdentifier): array
    {
        if (!array_key_exists($itemIdentifier, $this->comments)) {
            return [];
        }

        return $this->comments[$itemIdentifier];
    }
}
