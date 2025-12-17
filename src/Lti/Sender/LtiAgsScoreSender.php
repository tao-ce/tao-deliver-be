<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\Sender;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Environment\FeatureFlagAdapterInterface;
use App\Messenger\Message\ResultExtractionMessage;
use App\Service\DeliveryExecution\ScoringEligibilityChecker;
use App\Service\Lti\LtiAgsScoreService;
use OAT\Library\Lti1p3Ags\Model\Score\Score;
use Psr\Log\LoggerInterface;

class LtiAgsScoreSender implements LtiResultSenderInterface
{
    private const SCORING_OWNS_GRADING_PROGRESS = 'SCORING_OWNS_GRADING_PROGRESS';

    public function __construct(
        private ScoringEligibilityChecker $scoringEligibilityChecker,
        private LtiAgsScoreService $ltiAgsScoreService,
        private LoggerInterface $auditPlatformLogger,
        private FeatureFlagAdapterInterface $featureFlagAdapter,
    ) {
    }

    public function send(
        DeliveryExecution $deliveryExecution,
        array $resultData,
        ResultExtractionMessage $message,
    ): void {
        $ltiParameters = $deliveryExecution->getLtiLaunchParameters();
        if (empty($ltiParameters['ags_claim'])) {
            return;
        }

        $scoringOwnsGradingEnabled = $this->featureFlagAdapter->isEnabled(
            $deliveryExecution->getTenantId(),
            self::SCORING_OWNS_GRADING_PROGRESS,
        );

        if (
            $this->ltiAgsScoreService->send(
                $ltiParameters,
                $deliveryExecution->getFinishedAt(),
                $resultData['totalScore'],
                $resultData['maxScore'],
                Score::ACTIVITY_PROGRESS_STATUS_COMPLETED,
                $scoringOwnsGradingEnabled
                && $this->scoringEligibilityChecker->isEligible($deliveryExecution)
                    ? Score::GRADING_PROGRESS_STATUS_NOT_READY
                    : Score::GRADING_PROGRESS_STATUS_FULLY_GRADED,
            )
        ) {
            $this->auditPlatformLogger->info(
                sprintf(
                    '[%s] LTI AGS score publication was successful',
                    $deliveryExecution->getOriginalId(),
                ),
            );
        }
    }
}
