<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Extractor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Environment\FeatureFlagAdapterInterface;
use qtism\runtime\tests\AssessmentItemSession;

readonly class ItemResponseStatusResolver
{
    public function __construct(private FeatureFlagAdapterInterface $featureFlagAdapter)
    {
    }

    public function isRespondedTo(AssessmentItemSession $itemSession, DeliveryExecution $deliveryExecution): bool
    {
        return $deliveryExecution->getItemAttachments($itemSession->getAssessmentItem()->getIdentifier())
            || $itemSession->isResponded(
                !$this->featureFlagAdapter->isEnabled(
                    $deliveryExecution->getTenantId(),
                    'FEATURE_FLAG_REQUIRE_RESPONSE_TO_ALL_ITEM_INTERACTIONS',
                ),
            );
    }
}
