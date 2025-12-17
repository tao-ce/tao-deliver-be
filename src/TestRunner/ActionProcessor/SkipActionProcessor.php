<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Event\DeliveryExecutionClosedEvent;
use App\TestRunner\Event\TestSessionInteractionEvent;
use App\TestRunner\Generator\TestContextGenerator;
use App\TestRunner\Service\BatteryNavigationService;
use App\TestRunner\Service\GetItemService;
use App\TestRunner\Service\ItemSessionService;
use App\TestRunner\Service\TestSessionNavigator;
use Psr\Log\LoggerInterface;
use qtism\runtime\tests\AssessmentItemSessionState;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SkipActionProcessor extends AbstractActionProcessor
{
    public const ACTION_NAME = 'skip';

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TestContextGenerator $testContextGenerator,
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly TestSessionNavigator $testSessionNavigator,
        private readonly ItemSessionService $itemSessionService,
        private readonly BatteryNavigationService $batteryNavigationService,
        private readonly LoggerInterface $auditDeliveryExecutionLogger,
        private readonly GetItemService $itemService,
    ) {
    }

    public function getActionName(): string
    {
        return self::ACTION_NAME;
    }

    public function process(DeliveryExecution $deliveryExecution, array $actionParameters): array
    {
        $parameters = $actionParameters['parameters'];
        $itemIdentifier = $parameters['itemIdentifier'];
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        if ($testSession->getCurrentAssessmentItemRef()->getIdentifier() !== $itemIdentifier) {
            throw ConcurrentProcessException::createMultipleActivitySessionException();
        }

        // Filtering out { "touched": true/false } states that are otherwise empty
        $lastItemSession = $testSession->getCurrentAssessmentItemSession();
        $isLastSuspended = $lastItemSession->getState() === AssessmentItemSessionState::SUSPENDED;
        $decodedItemState = $isLastSuspended
            ? []
            : array_filter((array)json_decode($parameters['itemState'] ?? '[]', true), 'is_array');

        // Prevent the attempt from being consumed when skipping an item
        if (!$isLastSuspended && $lastItemSession->isAttempting()) {
            $lastItemSession['numAttempts']->setValue($lastItemSession['numAttempts']->getValue() - 1);
            $lastItemSession->endAttempt();
        }
        $toolState = $parameters['toolStates'] ?? null;
        $scope = empty($parameters['scope']) ? TestSessionNavigator::SCOPE_ITEM : $parameters['scope'];
        $direction = empty($parameters['direction']) ? TestSessionNavigator::DIRECTION_NEXT : $parameters['direction'];

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - test taker has skipped the the current item: [%s]',
                $deliveryExecution->getId(),
                $itemIdentifier,
            ),
        );

        if (!empty($decodedItemState)) {
            $itemState = json_encode($decodedItemState);
            $deliveryExecution->addTemporaryItemState(
                $itemIdentifier,
                $itemState,
            );
        }

        if (null !== $toolState) {
            $deliveryExecution->addToolState($toolState);

            $this->auditDeliveryExecutionLogger->info(
                sprintf(
                    '[%s] - test taker has updated the tool states: [%s]',
                    $deliveryExecution->getId(),
                    $toolState,
                ),
            );
        }

        $this->testSessionNavigator->navigate(
            $deliveryExecution,
            $scope,
            $direction,
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
        } elseif (
            $testSession->getCurrentAssessmentItemSession()->getRemainingAttempts() !== 0
            // If we didn't move, it would mean the last item in the current test part was submitted
            && $testSession->getCurrentAssessmentItemRef()->getIdentifier() !== $itemIdentifier
        ) {
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
