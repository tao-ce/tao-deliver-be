<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\Proctoring\AcsActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionStatus;
use App\Lti\LtiCustomSettings;
use App\Lti\Service\LtiExtraTimeHandler;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Service\TestSessionShutdownService;
use App\TestRunner\Service\TimerService;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResultInterface;
use OAT\Library\TaoTimerClient\Service\ProctoringAcsService;
use OAT\Library\TaoTimerClient\Service\TimerServiceInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AcsTerminateActionProcessor implements AcsActionProcessorInterface
{
    use AcsActionProcessorTrait;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ProctoringAcsService $proctoringAcsService,
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly DeliveryExecutionServiceInterface $deliveryExecutionService,
        private readonly TimerService $timerService,
        private readonly TestSessionShutdownService $sessionShutdownService,
        private readonly LtiCustomSettings $ltiCustomSettings,
        private readonly LtiExtraTimeHandler $ltiExtraTimeHandler,
        private readonly TimerServiceInterface $clientTimerService,
    ) {
    }

    public function supports(AcsControlInterface $acsControl): bool
    {
        return $acsControl->getAction() === AcsControlInterface::ACTION_TERMINATE;
    }

    public function process(
        AcsControlInterface $acsControl,
        DeliveryExecution $deliveryExecution,
        float $itemDuration = .0,
    ): AcsControlResultInterface {
        $this->sessionShutdownService->endTestSession(
            $deliveryExecution,
            DeliveryExecutionStatus::STATUS_TERMINATED,
        );

        return $this->sendAction(
            $acsControl,
            $deliveryExecution,
        );
    }
}
