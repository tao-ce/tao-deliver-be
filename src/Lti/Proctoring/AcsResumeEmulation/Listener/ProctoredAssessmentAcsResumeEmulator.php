<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\Proctoring\AcsResumeEmulation\Listener;

use App\Lti\Event\AcsControlProcessedEvent;
use App\Lti\Proctoring\AcsActionProcessor\AcsActionProcessorInterface;
use App\Lti\Proctoring\AcsResumeEmulation\Event\ProctoredAssessmentForceResumed;
use Carbon\Carbon;
use OAT\Library\Lti1p3Core\Resource\LtiResourceLink\LtiResourceLink;
use OAT\Library\Lti1p3Proctoring\Model\AcsControl;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResultInterface;
use OAT\Library\TaoTimerClient\Service\ProctoringAcsService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(ProctoredAssessmentForceResumed::class, 'onProctoredAssessmentForceResumed')]
class ProctoredAssessmentAcsResumeEmulator
{
    public function __construct(
        private readonly ProctoringAcsService $proctoringAcsService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function onProctoredAssessmentForceResumed(ProctoredAssessmentForceResumed $event): void
    {
        $deliveryExecution = $event->deliveryExecution;
        $acsControl = $this->createResumeAcsActionFromParameters($deliveryExecution->getLtiLaunchParameters());
        $this->proctoringAcsService->sendAction(
            $deliveryExecution->getId(),
            $acsControl,
        );

        $this->eventDispatcher->dispatch(new AcsControlProcessedEvent(
            $deliveryExecution,
            AcsActionProcessorInterface::STATUSES_MAP[$deliveryExecution->getStatus()] ?? AcsControlResultInterface::STATUS_NONE,
            $acsControl,
        ));
    }

    private function createResumeAcsActionFromParameters(array $ltiLaunchParameters): AcsControlInterface
    {
        return new AcsControl(
            new LtiResourceLink($ltiLaunchParameters['resource_link_id']),
            $ltiLaunchParameters['client_id'],
            AcsControlInterface::ACTION_RESUME,
            Carbon::now(),
            issuerIdentifier: $ltiLaunchParameters['platform_issuer'],
        );
    }
}
