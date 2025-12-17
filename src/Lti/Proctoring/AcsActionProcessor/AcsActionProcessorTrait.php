<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\Proctoring\AcsActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Lti\Service\LtiExtraTimeHandler;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Event\TestSessionInteractionEvent;
use JsonException;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResult;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlResultInterface;
use OAT\Library\TaoTimerClient\Client\GetTimerException;
use OAT\Library\TaoTimerClient\Service\Exception\ProctoringAcsGetExtraTimeFailedException;
use OAT\Library\TaoTimerClient\Service\Exception\ProctoringAcsSendActionFailedException;
use OAT\Library\TaoTimerClient\Service\ProctoringAcsService;
use OAT\Library\TaoTimerClient\Service\TimerServiceInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

trait AcsActionProcessorTrait
{
    private readonly EventDispatcherInterface $eventDispatcher;
    private readonly ProctoringAcsService $proctoringAcsService;
    private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService;
    private readonly LtiCustomSettings $ltiCustomSettings;
    private readonly LtiExtraTimeHandler $ltiExtraTimeHandler;
    private readonly TimerServiceInterface $clientTimerService;

    /**
     * @throws ClientExceptionInterface
     * @throws ProctoringAcsGetExtraTimeFailedException
     * @throws JsonException
     * @throws ProctoringAcsSendActionFailedException
     */
    protected function sendAction(
        AcsControlInterface $acsControl,
        DeliveryExecution $deliveryExecution,
    ): AcsControlResultInterface {
        $baseLineExtraTime = $this->ltiCustomSettings->getExtraTime($deliveryExecution->getLtiLaunchParameters());

        if ($deliveryExecution->doesBelongToBattery()) {
            $previousDeliveryExecutionTimer = $this->ltiExtraTimeHandler->getPreviousDeliveryExecutionTimer(
                $deliveryExecution,
                $deliveryExecution->getDeliveryId(),
                true,
            );

            if ($previousDeliveryExecutionTimer) {
                $initialExtraValue = $previousDeliveryExecutionTimer->getExtra()?->getInitialValue();

                if ($initialExtraValue) {
                    $baseLineExtraTime = (int)ceil($initialExtraValue / 1000 / 60);
                }
            }
        }

        if ($acsControl->getExtraTime() < $baseLineExtraTime) {
            $acsControl->setExtraTime($baseLineExtraTime);
        }

        $this->proctoringAcsService->sendAction(
            $deliveryExecution->getId(),
            $acsControl,
        );

        $this->eventDispatcher->dispatch(
            new TestSessionInteractionEvent(
                self::class,
                $acsControl->getAction(),
                $deliveryExecution,
                $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution),
            ),
        );

        return new AcsControlResult(
            AcsActionProcessorInterface::STATUSES_MAP[$deliveryExecution->getStatus()] ?? AcsControlResultInterface::STATUS_NONE,
            $this->proctoringAcsService->getExtraTime($deliveryExecution->getId()),
        );
    }
}
