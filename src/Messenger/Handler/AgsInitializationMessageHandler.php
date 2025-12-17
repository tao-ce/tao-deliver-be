<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Messenger\Message\AgsInitializationMessage;
use App\Service\Ags\AgsInitializationService;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @deprecated Keeping it alive for backward compatibility
 *             with any of the messages that may still be in the queue at the time of deployment.
 */
#[AsMessageHandler]
class AgsInitializationMessageHandler
{
    public function __construct(
        private RepositoryAwareDeliveryExecutionServiceInterface $loggerAwareDeliveryExecutionService,
        private AgsInitializationService $agsInitializationService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(AgsInitializationMessage $agsInitializationMessage)
    {
        $deliveryExecutionId = $agsInitializationMessage->getDeliveryExecutionId();

        try {
            $deliveryExecution = $this->loggerAwareDeliveryExecutionService->findDeliveryExecutionOrFail($deliveryExecutionId);
        } catch (DocumentNotFoundException $exception) {
            $this->logger->warning(
                sprintf('[%s] - cannot initialize ags for nonexistent delivery execution', $deliveryExecutionId),
                compact('exception'),
            );

            return;
        }

        $this->agsInitializationService->init($deliveryExecution, $agsInitializationMessage->getTimestamp());
    }
}
