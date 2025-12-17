<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use Psr\Log\LoggerInterface;

class SaveItemStateActionProcessor extends AbstractActionProcessor
{
    private const ACTION_NAME = 'saveItemState';

    public function __construct(
        private DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private LoggerInterface $auditDeliveryExecutionLogger,
    ) {
    }

    public function getActionName(): string
    {
        return static::ACTION_NAME;
    }

    public function process(DeliveryExecution $deliveryExecution, array $actionParameters): array
    {
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $parameters = $actionParameters['parameters'];
        $itemState = $parameters['itemState'];
        $itemIdentifier = $parameters['itemIdentifier'];

        $currentAssessmentItemRef = $testSession->getCurrentAssessmentItemRef();
        if ($currentAssessmentItemRef !== false && $currentAssessmentItemRef->getIdentifier() !== $itemIdentifier) {
            throw ConcurrentProcessException::createMultipleActivitySessionException();
        }

        $deliveryExecution->addTemporaryItemState(
            $testSession->getCurrentAssessmentItemRef()->getIdentifier(),
            $itemState,
        );

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - itemState: [%s] for item - [%s] was stored',
                $deliveryExecution->getId(),
                mb_strimwidth($itemState, 0, static::MAX_LOG_SIZE, '...'),
                $itemIdentifier,
            ),
        );

        return $this->getActionProcessorResponse($actionParameters, []);
    }
}
