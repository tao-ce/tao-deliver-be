<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Ags;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\Lti\LtiAgsScoreService;
use Carbon\Carbon;
use DateTimeInterface;
use OAT\Library\Lti1p3Ags\Model\Score\ScoreInterface;
use Psr\Log\LoggerInterface;

class AgsInitializationService
{
    public function __construct(
        private LtiAgsScoreService $ltiAgsScoreService,
        private LoggerInterface $auditDeliveryExecutionLogger,
    ) {
    }

    public function init(DeliveryExecution $deliveryExecution, ?DateTimeInterface $timestamp = null): void
    {
        if (
            $this->ltiAgsScoreService->send(
                $deliveryExecution->getLtiLaunchParameters(),
                $timestamp ?? Carbon::now(),
                null,
                null,
                ScoreInterface::ACTIVITY_PROGRESS_STATUS_STARTED,
                ScoreInterface::GRADING_PROGRESS_STATUS_NOT_READY,
            )
        ) {
            $this->auditDeliveryExecutionLogger->info(
                sprintf(
                    '[%s] - LTI score published with status [%s]',
                    $deliveryExecution->getId(),
                    ScoreInterface::GRADING_PROGRESS_STATUS_NOT_READY,
                ),
            );
        }
    }
}
