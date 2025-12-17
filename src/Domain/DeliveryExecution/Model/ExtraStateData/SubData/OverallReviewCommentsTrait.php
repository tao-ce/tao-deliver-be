<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

use App\Domain\DeliveryExecution\Model\ExtraStateData\OverallComment;

trait OverallReviewCommentsTrait
{
    /** @var array<OverallComment> */
    protected array $itemsOverallComments = [];

    public function withItemOverallComment(string $id, OverallComment $itemOverallComment): static
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->itemsOverallComments[$id] = $itemOverallComment;

        return $deliveryExecutionExtraStateData;
    }

    public function getItemOverallReviewComment(string $itemId): ?OverallComment
    {
        return $this->itemsOverallComments[$itemId] ?? null;
    }

    public function getArrayItemOverallComments(): array
    {
        return $this->itemsOverallComments;
    }
}
