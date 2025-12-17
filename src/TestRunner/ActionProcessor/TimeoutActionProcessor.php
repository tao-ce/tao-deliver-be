<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Qti\DataType\AssessmentTestPlace;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Event\TestSessionInteractionEvent;
use App\TestRunner\Generator\TestContextGenerator;
use App\TestRunner\Service\ItemSessionService;
use App\TestRunner\Service\TimerService;
use Psr\Log\LoggerInterface;
use qtism\data\QtiIdentifiable;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\AssessmentTestSessionException;
use qtism\runtime\tests\AssessmentTestSessionState;
use qtism\runtime\tests\TimeConstraint;
use qtism\runtime\tests\TimeConstraintCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class TimeoutActionProcessor extends AbstractActionProcessor
{
    public const ACTION_NAME = 'timeout';

    public function __construct(
        private readonly TestContextGenerator $testContextGenerator,
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly ItemSessionService $itemSessionService,
        private readonly TimerService $timerService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $auditDeliveryExecutionLogger,
    ) {
    }

    public function getActionName(): string
    {
        return self::ACTION_NAME;
    }

    public function process(DeliveryExecution $deliveryExecution, array $actionParameters): array
    {
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        $parameters = $actionParameters['parameters'];

        if ($testSession->getCurrentAssessmentItemRef()->getIdentifier() !== $parameters['itemIdentifier']) {
            throw ConcurrentProcessException::createMultipleActivitySessionException();
        }

        $toolState = $parameters['toolStates'] ?? null;

        if (null !== $toolState) {
            $deliveryExecution->addToolState($toolState);
        }

        if (!$this->endStateAttempt($testSession, $deliveryExecution, $parameters)) {
            $this->timerService->endServerTimer(
                $deliveryExecution,
                $testSession,
                (float)$parameters['itemDuration'],
            );
        }
        $testSession->suspend();
        $this->deliveryExecutionPropertyService->persistTestSession($testSession);

        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - test taker has reached the timeout for item: [%s] wth itemResponse: [%s]',
                $deliveryExecution->getId(),
                $parameters['itemIdentifier'],
                $parameters['itemResponse'] ?? '{}',
            ),
        );

        $this->logTimeout($deliveryExecution, $testSession, $parameters);

        $this->eventDispatcher->dispatch(
            new TestSessionInteractionEvent(self::class, $this->getActionName(), $deliveryExecution, $testSession),
        );

        return $this->getActionProcessorResponse($actionParameters, [
            'testContext' => $this->testContextGenerator->generate(
                $testSession,
                $deliveryExecution,
            ),
        ]);
    }

    private function logTimeout(
        DeliveryExecution $deliveryExecution,
        AssessmentTestSession $testSession,
        array $parameters,
    ): void {
        /** @var TimeConstraint $constraint */
        foreach ($testSession->getTimeConstraints() as $constraint) {
            /** @var QtiIdentifiable $qtiIdentifiable */
            $qtiIdentifiable = $constraint->getSource();

            /** @var string $identifier */
            $identifier = $qtiIdentifiable->getIdentifier();
            if (0 === strcasecmp($identifier, $parameters['itemIdentifier'])) {
                $this->auditDeliveryExecutionLogger->info(
                    sprintf(
                        '[%s] - the following timer has expired for type: %s',
                        $deliveryExecution->getId(),
                        get_class($qtiIdentifiable),
                    ),
                );
            }
        }
    }

    private function endStateAttempt(
        AssessmentTestSession $testSession,
        DeliveryExecution $deliveryExecution,
        array $requestParameters,
    ): bool {
        if (empty($requestParameters['itemResponse']) || empty($requestParameters['itemState'])) {
            return false;
        }

        $itemIdentifier = $testSession->getCurrentAssessmentItemRef()->getIdentifier();
        $deliveryExecution->removeTemporaryItemState($itemIdentifier);

        $itemState = $requestParameters['itemState'];
        if ($itemState === $deliveryExecution->getExtraStateData()->getItemState($itemIdentifier)) {
            return false;
        }

        $timeConstraints = $testSession->getTimeConstraints(
            AssessmentTestPlace::getConstantByName($requestParameters['scope']),
        );

        // Save the state as the temp one on a timeout even if the late submission is disallowed
        // That can help in cases when the assessment is later reopened and extended
        $deliveryExecution->addTemporaryItemState($itemIdentifier, $itemState);

        if (
            !$this->isLateResponseSubmissionAllowed($timeConstraints)
            || in_array(
                $testSession->getState(),
                [AssessmentTestSessionState::INITIAL, AssessmentTestSessionState::CLOSED],
                true,
            )
        ) {
            return false;
        }

        try {
            $this->itemSessionService->submitResponse(
                $deliveryExecution,
                json_decode($requestParameters['itemResponse'], true),
                (float)$requestParameters['itemDuration'],
                $itemState,
            );
        } catch (AssessmentTestSessionException $exception) {
            if (
                !in_array(
                    $exception->getCode(),
                    [
                        AssessmentTestSessionException::ASSESSMENT_ITEM_INVALID_RESPONSE,
                        AssessmentTestSessionException::ASSESSMENT_ITEM_SKIPPING_FORBIDDEN,
                    ],
                    true,
                )
            ) {
                // There's probably no point in bubbling this up to the UI level
                $this->auditDeliveryExecutionLogger->critical($exception->getMessage(), ['exception' => $exception]);
            }
            return false;
        }

        return true;
    }

    private function isLateResponseSubmissionAllowed(TimeConstraintCollection $timeConstraints): bool
    {
        /** @var TimeConstraint $timeConstraint */
        foreach ($timeConstraints as $timeConstraint) {
            if ($timeConstraint->allowLateSubmission()) {
                return true;
            }
            if (
                $timeConstraint->maxTimeInForce()
                && $timeConstraint->minTimeInForce()
                && $timeConstraint->getSource()->getTimeLimits()->getMaxTime()->equals(
                    $timeConstraint->getSource()->getTimeLimits()->getMinTime(),
                )
            ) {
                return true;
            }
        }

        return false;
    }
}
