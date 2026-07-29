<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Environment\FeatureFlagAdapterInterface;
use App\Lti\LtiCustomSettings;
use App\Service\Lti\LtiTokenResolverInterface;

readonly class DeliveryExecutionCommentService
{
    public function __construct(
        private LtiTokenResolverInterface $ltiTokenResolver,
        private LtiCustomSettings $customSettings,
        private FeatureFlagAdapterInterface $featureFlagAdapter,
    ) {
    }

    public function getItemFeedback(DeliveryExecution $deliveryExecution, string $itemId): array
    {
        if ($this->ltiTokenResolver->hasOneOfRoles([LtiTokenResolverInterface::LTI_ROLE_INSTRUCTOR])) {
            return $deliveryExecution->getReviewInlineCommentForItem(
                $this->getInlineFeedbackScorerId($deliveryExecution),
                $itemId,
            );
        }

        if (
            !$this->ltiTokenResolver->hasOneOfRoles([LtiTokenResolverInterface::LTI_ROLE_LEARNER])
            || !$deliveryExecution->isItemScoredExternally($itemId)
        ) {
            return [];
        }

        return $deliveryExecution->getReviewInlineCommentForItem(null, $itemId);
    }

    public function addItemFeedback(DeliveryExecution $deliveryExecution, string $itemId, array $feedback): void
    {
        $deliveryExecution->addReviewInlineComment($this->getInlineFeedbackScorerId($deliveryExecution), $itemId, $feedback);
    }

    public function getItemAnnotationComment(DeliveryExecution $deliveryExecution, string $itemId): array
    {
        if ($this->ltiTokenResolver->hasOneOfRoles([LtiTokenResolverInterface::LTI_ROLE_INSTRUCTOR])) {
            return $deliveryExecution->getAnnotationCommentForItem(
                $this->getScorerId($deliveryExecution),
                $itemId,
            );
        }

        return [];
    }

    public function addItemAnnotationComment(DeliveryExecution $deliveryExecution, string $itemId, array $feedback): void
    {
        $deliveryExecution->addAnnotationComment($this->getScorerId($deliveryExecution), $itemId, $feedback);
    }

    private function getInlineFeedbackScorerId(DeliveryExecution $deliveryExecution): ?string
    {
        return $this->featureFlagAdapter->isEnabled($deliveryExecution->getTenantId(), 'SEGREGATE_GRADER_FEEDBACK')
            ? $this->getScorerId($deliveryExecution)
            : null;
    }

    private function getScorerId(DeliveryExecution $deliveryExecution): ?string
    {
        return $this->customSettings->getScorerId($deliveryExecution->getLtiLaunchParameters());
    }
}
