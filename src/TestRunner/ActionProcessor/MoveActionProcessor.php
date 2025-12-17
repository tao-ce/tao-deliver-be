<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\ActionProcessor\Handler\CantPerformActionException;
use App\TestRunner\Event\DeliveryExecutionClosedEvent;
use App\TestRunner\Event\TestSessionInteractionEvent;
use App\TestRunner\Generator\TestContextGenerator;
use App\TestRunner\Service\BatteryNavigationService;
use App\TestRunner\Service\GetItemService;
use App\TestRunner\Service\ItemSessionService;
use App\TestRunner\Service\TestSessionNavigator;
use Psr\Log\LoggerInterface;
use qtism\runtime\rules\ProcessingCollectionException;
use qtism\runtime\tests\AssessmentItemSessionState;
use qtism\runtime\tests\AssessmentTestSessionException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MoveActionProcessor extends AbstractActionProcessor
{
    public const ACTION_NAME = 'move';

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly TestContextGenerator $testContextGenerator,
        private readonly ItemSessionService $itemSessionService,
        private readonly TestSessionNavigator $testSessionNavigator,
        private readonly LoggerInterface $auditDeliveryExecutionLogger,
        private readonly BatteryNavigationService $batteryNavigationService,
        private readonly GetItemService $itemService,
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

        $itemIdentifier = $parameters['itemIdentifier'];
        if ($testSession->getCurrentAssessmentItemRef()->getIdentifier() !== $itemIdentifier) {
            throw ConcurrentProcessException::createMultipleActivitySessionException();
        }

        $itemState = $parameters['itemState'] ?? null;
        $toolState = $parameters['toolStates'] ?? null;

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - test taker has submitted the following item: [%s] with ItemResponse: [%s] and itemState: [%s] ',
                $deliveryExecution->getId(),
                $itemIdentifier,
                mb_strimwidth($parameters['itemResponse'], 0, static::MAX_LOG_SIZE, '...'),
                mb_strimwidth($itemState, 0, static::MAX_LOG_SIZE, '...'),
            ),
        );

        if (null !== $itemState) {
            // Preserve the temporary item state in case the actual response submission errors out
            $deliveryExecution->addTemporaryItemState(
                $testSession->getCurrentAssessmentItemRef()->getIdentifier(),
                $itemState,
            );
        }

        if (null !== $toolState) {
            $deliveryExecution->addToolState($toolState);
            $this->auditDeliveryExecutionLogger->info(
                sprintf(
                    '[%s] - test taker has updated the tool states: [%s] ',
                    $deliveryExecution->getId(),
                    $toolState,
                ),
            );
        }
        try {
            if ($testSession->getCurrentAssessmentItemSession()->getState() === AssessmentItemSessionState::INTERACTING) {
                $this->itemSessionService->submitResponse(
                    $deliveryExecution,
                    json_decode($parameters['itemResponse'], true),
                    (float)$parameters['itemDuration'],
                    $itemState,
                );
            }
        } catch (AssessmentTestSessionException $exception) {
            $originalException = $exception->getPrevious();

            if (!$originalException instanceof ProcessingCollectionException) {
                throw $exception;
            }

            foreach ($originalException->getProcessingExceptions() as $processingException) {
                $this->auditDeliveryExecutionLogger->error(
                    sprintf(
                        '[%s] - An error occurred while processing the response: %s',
                        $deliveryExecution->getId(),
                        $processingException->getMessage(),
                    ),
                );
            }
        }

        $this->testSessionNavigator->navigate(
            $deliveryExecution,
            $parameters['scope'],
            $parameters['direction'],
            $parameters['ref'] ?? null,
        );

        $closingSession = !$testSession->isRunning();

        if ($closingSession) {
            $deliveryExecution->close();
            $this->eventDispatcher->dispatch(new DeliveryExecutionClosedEvent($deliveryExecution));

            $this->auditDeliveryExecutionLogger->info(
                sprintf(
                    '[%s] - test taker has submitted the delivery execution',
                    $deliveryExecution->getId(),
                ),
            );
        } elseif ($testSession->getCurrentAssessmentItemSession()->getRemainingAttempts() !== 0) {
            $this->itemSessionService->beginAttempt($deliveryExecution);
        }

        $this->deliveryExecutionPropertyService->persistTestSession($testSession);

        $this->eventDispatcher->dispatch(
            new TestSessionInteractionEvent(self::class, $this->getActionName(), $deliveryExecution, $testSession),
        );

        $responseParameters = [
            'testContext' => $this->testContextGenerator->generate(
                $testSession,
                $deliveryExecution,
            ),
        ];

        if ($closingSession) {
            $batteryContext = $this->batteryNavigationService->getBatteryContext(
                $deliveryExecution,
                $actionParameters,
            );

            if ($batteryContext !== null) {
                $responseParameters['batteryContext'] = $batteryContext;
            }
        }

        $dynamicItemData = $testSession->isRunning()
            ? $this->itemService->getItemDynamicData(
                $deliveryExecution,
                $testSession->getCurrentAssessmentItemRef()->getIdentifier(),
            )
            : [];

        $response = $this->getActionProcessorResponse($actionParameters, $responseParameters);
        $response['values'] = array_merge(
            $response['values'],
            $dynamicItemData,
        );

        return $response;
    }
}
