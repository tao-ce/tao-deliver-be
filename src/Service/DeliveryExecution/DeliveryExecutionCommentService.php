<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionCommentServiceInterface;

class DeliveryExecutionCommentService implements DeliveryExecutionCommentServiceInterface
{
    public function addItemReviewComment(DeliveryExecution $deliveryExecution, string $itemId, mixed $comment): void
    {
        $deliveryExecution->addReviewInlineComment($itemId, $comment);
    }
}
