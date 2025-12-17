<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Messenger\Message\QtiClassValueCleanUpMessage;
use App\Messenger\Message\ResultExtractionMessage;
use App\Registry\LtiResultSenderRegistry;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\ExtractDeliveryExecutionResultService;
use App\TestRunner\Event\DeliveryExecutionScoredEvent;
use App\TestRunner\Service\DeliveryExecutionClosureService;
use App\TestRunner\Service\TestSessionInitiator;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler]
readonly class ResultExtractionMessageHandler
{
    public function __construct(
        private RepositoryAwareDeliveryExecutionServiceInterface $loggerAwareDeliveryExecutionService,
        private ExtractDeliveryExecutionResultService $extractDeliveryExecutionResultService,
        private DeliveryExecutionClosureService $deliveryExecutionClosureService,
        private LoggerInterface $auditPlatformLogger,
        private LtiResultSenderRegistry $resultSender,
        private TestSessionInitiator $sessionInitiator,
        private MessageBusInterface $messageBus,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(ResultExtractionMessage $message): void
    {
        $deliveryExecution = $this->loggerAwareDeliveryExecutionService->findDeliveryExecution(
            $message->getDeliveryExecutionId(),
        );
        if (!$deliveryExecution) {
            if ($message->isForceClosure()) {
                return; // ignore the force-closure request; delivery execution was never created
            }
            throw new RuntimeException("[{$message->getDeliveryExecutionId()}] not found; retrying.");
        }

        if ($message->isForceClosure()) {
            // This will ensure the delivery execution can be closed
            $this->sessionInitiator->init($deliveryExecution);
            $this->deliveryExecutionClosureService->close($deliveryExecution);

            // The service above will re-trigger the results extraction
            // A better approach might be introducing another command/message to force-close a delivery execution
            return;
        }

        if ($deliveryExecution->getFinishedAt() === null) {
            $this->auditPlatformLogger->warning(
                sprintf('[%s] Delivery execution was not finished', $deliveryExecution->getId()),
            );
            return;
        }

        $resultData = $this->extractDeliveryExecutionResultService->extract($deliveryExecution);

        $this->auditPlatformLogger->info(
            sprintf('[%s] Result/Scoring was extracted and processed', $deliveryExecution->getOriginalId()),
        );

        /** remove files which attached to extended text but not used */
        $this->messageBus->dispatch(
            new QtiClassValueCleanUpMessage(
                $deliveryExecution->getOriginalId(),
            ),
        );

        $this->eventDispatcher->dispatch(
            new DeliveryExecutionScoredEvent($deliveryExecution),
        );

        foreach ($this->resultSender->getSenders() as $sender) {
            $sender->send($deliveryExecution, $resultData, $message);
        }
    }
}
