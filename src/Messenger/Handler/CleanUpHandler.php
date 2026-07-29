<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Messenger\Message\QtiClassValueCleanUpMessage;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use App\TestRunner\Service\ExternalTimerService;
use JsonException;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CleanUpHandler
{
    public function __construct(
        private RepositoryAwareDeliveryExecutionServiceInterface $loggerAwareDeliveryExecutionService,
        private LoggerInterface $auditPlatformLogger,
        private ExternalTimerService $timerService,
        private DeliveryExecutionServiceInterface $deliveryExecutionService,
    ) {
    }

    /**
     * @throws RuntimeException
     */
    public function __invoke(QtiClassValueCleanUpMessage $message): void
    {
        try {
            $deliveryExecution = $this->loggerAwareDeliveryExecutionService
                ->findDeliveryExecutionOrFail($message->getDeliveryExecutionId());

            // It might be that the delivery execution's been re-opened by the time we get here
            if (!$deliveryExecution->isStateFinal()) {
                return;
            }

            // remove related timers
            $externalTimerDefinition = $this->timerService->getServerTimer($deliveryExecution);
            if ($externalTimerDefinition !== null) {
                $this->timerService->deleteServerTimer($deliveryExecution->getId());
                $deliveryExecution->addExternalTimerDefinition($externalTimerDefinition);
                $this->deliveryExecutionService->saveDeliveryExecution($deliveryExecution);
            }
            $this->removeHistoryFromItemState($deliveryExecution);
        } catch (DocumentNotFoundException $e) {
            $this->auditPlatformLogger->warning($e->getMessage());
        }
    }

    private function removeHistoryFromItemState(DeliveryExecution $deliveryExecution): void
    {
        $itemStates = $deliveryExecution->getExtraStateData()->getItemStates();
        $modify = false;

        foreach ($itemStates as $itemId => $itemStateJson) {
            try {
                $itemState = json_decode($itemStateJson, true, 512, JSON_THROW_ON_ERROR);
                if (isset($itemState['RESPONSE']['history'])) {
                    unset($itemState['RESPONSE']['history']);
                    $deliveryExecution->addItemState($itemId, json_encode($itemState));
                    $modify = true;
                }
            } catch (JsonException) {
                // ignore invalid states
            }
        }

        if ($modify) {
            $this->deliveryExecutionService->saveDeliveryExecution($deliveryExecution);
        }
    }
}
