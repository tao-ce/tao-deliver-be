<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Generator\UuidGenerator;
use App\Lti\LtiCustomSettings;
use App\Repository\BatteryDistributionRepository;
use App\Repository\DeliveryExecutionRepository;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use JsonException;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Library\TaoTimerClient\Client\CreateTimerException;
use OAT\Library\TaoTimerClient\Client\DeleteTimerException;
use OAT\Library\TaoTimerClient\Client\GetTimerException;
use OAT\Library\TaoTimerClient\Model\Contract\TimerDefinitionInterface;
use OAT\Library\TaoTimerClient\Model\TimerDefinition;
use OAT\Library\TaoTimerClient\Model\TimerDetail;
use OAT\Library\TaoTimerClient\Service\Exception\ProctoringAcsGetExtraTimeFailedException;
use OAT\Library\TaoTimerClient\Service\Exception\UnexpectedJsonFormatException;
use OAT\Library\TaoTimerClient\Service\ProctoringAcsService;
use OAT\Library\TaoTimerClient\Service\TimerServiceInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;

readonly class LtiExtraTimeHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private UuidGenerator $uuidGenerator,
        private ProctoringAcsService $proctoringAcsService,
        private TimerServiceInterface $timerService,
        private LtiCustomSettings $ltiCustomSettings,
        private BatteryDistributionRepository $batteryDistributionRepository,
        private DeliveryExecutionRepository $deliveryExecutionRepository,
        private DeliveryExecutionService $deliveryExecutionService,
    ) {
    }

    /**
     * @throws DocumentNotFoundException
     * @throws ProctoringAcsGetExtraTimeFailedException
     * @throws UnexpectedJsonFormatException
     * @throws JsonException
     * @throws ClientExceptionInterface
     */
    public function addExtraTime(DeliveryExecution $deliveryExecution): void
    {
        $extraTime = $this->ltiCustomSettings->getExtraTime($deliveryExecution->getLtiLaunchParameters());

        if ($extraTime === 0) {
            return;
        }

        if ($deliveryExecution->doesBelongToBattery()) {
            $this->addExtraTimeToBatteryDeliveryExecution($deliveryExecution, $extraTime);
        } else {
            $this->addExtraTimeToDeliveryExecution($deliveryExecution, $extraTime);
        }
    }

    public function getPreviousDeliveryExecutionTimer(
        DeliveryExecution $deliveryExecution,
        string $deliveryId,
        bool $fetchFromTimerService = false,
    ): ?TimerDefinitionInterface {
        $userId = $deliveryExecution->getLtiLaunchParameters()['user_id'];
        $batteryId = $deliveryExecution->getLtiLaunchParameters()['battery_id'];
        $attemptId = $deliveryExecution->getAttemptId();
        $batteryDistribution = $this->batteryDistributionRepository->findByBatteryAndUserId($batteryId, $userId, $attemptId);
        $previousBatteryDelivery = $batteryDistribution->battery->getPreviousDelivery($deliveryId);

        if (!$previousBatteryDelivery) {
            return null;
        }

        try {
            $previousDeliveryExecutionId = $this->deliveryExecutionService->createDeliveryExecutionId(
                $previousBatteryDelivery->id,
                $deliveryExecution->getTenantId(),
                $deliveryExecution->getLtiLaunchParameters(),
            );
            $previousDeliveryExecution = $this->deliveryExecutionRepository->find($previousDeliveryExecutionId);
        } catch (DocumentNotFoundException) {
            // previous delivery execution not found, let's try to find the previous one
            return $this->getPreviousDeliveryExecutionTimer(
                $deliveryExecution,
                $previousBatteryDelivery->id,
                $fetchFromTimerService,
            );
        }

        if (!$fetchFromTimerService) {
            return $previousDeliveryExecution
                ->getExtraStateData()
                ->getExternalTimerDefinition();
        }

        try {
            return $this->timerService->getTimer($previousDeliveryExecution->getId());
        } catch (GetTimerException) {
            return null;
        }
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     * @throws ProctoringAcsGetExtraTimeFailedException
     * @throws UnexpectedJsonFormatException
     */
    private function addExtraTimeToDeliveryExecution(DeliveryExecution $deliveryExecution, int $extraTime): void
    {
        $currentExtraTime = $this->proctoringAcsService->getExtraTime($deliveryExecution->getId());

        // Set extra time only if it is not already set
        if ($currentExtraTime === 0) {
            $timer = null;

            try {
                $timer = $this->timerService->getTimer($deliveryExecution->getId());

                $this->timerService->deleteTimer($deliveryExecution->getId());
            } catch (GetTimerException) {
                // if the timer is not found, it means that the test has no timers in it,
                // therefore we must create a new one
                $timer = new TimerDefinition();
            }

            $extra = new TimerDetail();
            $extra->setId($this->uuidGenerator->generate());
            $extra->setMaxTime($extraTime * 60 * 1000);
            $extra->setMaxTimeRemaining($extraTime * 60 * 1000);
            $extra->setInitialValue($extraTime * 60 * 1000); // we must store the initial session extra time as initial value

            $timer->setExtra($extra);

            $this->timerService->createTimer($deliveryExecution->getId(), $timer);

            $this->logger->info(sprintf(
                'Extra time %d minutes initial value has been added to delivery execution %s',
                $extraTime,
                $deliveryExecution->getId(),
            ));
        }
    }

    /**
     * @throws ClientExceptionInterface
     * @throws DocumentNotFoundException
     * @throws UnexpectedJsonFormatException
     * @throws ProctoringAcsGetExtraTimeFailedException
     * @throws JsonException
     */
    private function addExtraTimeToBatteryDeliveryExecution(DeliveryExecution $deliveryExecution, int $extraTime): void
    {
        try {
            $timer = $this->timerService->getTimer($deliveryExecution->getId());
        } catch (GetTimerException) {
            // If there is no timer for the current delivery execution, then this delivery has no timers
            return;
        }

        $previousDeliveryExecutionTimer = $this->getPreviousDeliveryExecutionTimer(
            $deliveryExecution,
            $deliveryExecution->getDeliveryId(),
        );

        if (!$previousDeliveryExecutionTimer) {
            $this->addExtraTimeToDeliveryExecution($deliveryExecution, $extraTime);
            return;
        }

        try {
            $extra = $previousDeliveryExecutionTimer->getExtra() ?? new TimerDetail();
            $handoverInitialValue = $extra->getMaxTimeRemaining() - ($extra->getMaxTime() - ($extra->getInitialValue() ?? 0));

            $extra->setId($this->uuidGenerator->generate());
            $extra->setInitialValue(max($handoverInitialValue, 0));
            $timer->setExtra($extra);

            $this->timerService->deleteTimer($deliveryExecution->getId());
            $this->timerService->createTimer($deliveryExecution->getId(), $timer);
        } catch (DeleteTimerException | CreateTimerException $exception) {
            // If for some reason we can't delete or create a timer, log and ignore this
            $this->logger->error(sprintf(
                'Failed to add extra time to delivery execution %s. Reason: %s',
                $deliveryExecution->getId(),
                $exception->getMessage(),
            ));

            return;
        }
    }
}
