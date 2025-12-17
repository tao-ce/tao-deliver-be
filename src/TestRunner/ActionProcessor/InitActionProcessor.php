<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\ActionProcessor\Exception\ConflictException;
use App\TestRunner\ActionProcessor\Handler\CantPerformActionException;
use App\TestRunner\Event\TestSessionInteractionEvent;
use App\TestRunner\Generator\TestContextGenerator;
use App\TestRunner\Generator\TestMapGenerator;
use App\TestRunner\Service\GetItemService;
use App\TestRunner\Service\TestSessionNavigator;
use App\TestRunner\Service\TimerService;
use App\Validator\Exception\RequestValidationException;
use Psr\Log\LoggerInterface;
use qtism\runtime\tests\AssessmentItemSessionState;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\AssessmentTestSessionState;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class InitActionProcessor extends AbstractActionProcessor
{
    public const ACTION_NAME = 'init';

    protected const AVAILABLE_STATUSES = [
        DeliveryExecution::STATUS_INTERACTING,
        DeliveryExecution::STATUS_SUSPENDED,
    ];

    private const ASSESSMENT_TEST_SESSION_STATUSES = [
        AssessmentTestSessionState::INTERACTING,
        AssessmentTestSessionState::SUSPENDED,
    ];

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly TestContextGenerator $testContextGenerator,
        private readonly TestMapGenerator $testMapGenerator,
        private readonly TimerService $timerService,
        private readonly LoggerInterface $auditDeliveryExecutionLogger,
        private readonly LtiCustomSettings $ltiCustomSettings,
        private readonly TestSessionNavigator $testSessionNavigator,
        private readonly GetItemService $itemService,
    ) {
    }

    public function getActionName(): string
    {
        return self::ACTION_NAME;
    }

    public function process(DeliveryExecution $deliveryExecution, array $actionParameters): array
    {
        if ($this->ltiCustomSettings->getStartRemainingWaitTime($deliveryExecution->getLtiLaunchParameters())) {
            throw CantPerformActionException::becauseUnavailableStatus($this->getActionName(), 'blocked');
        }

        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);


        $dynamicItemData = $testSession->isRunning()
            ? $this->itemService->getItemDynamicData(
                $deliveryExecution,
                $testSession->getCurrentAssessmentItemRef()->getIdentifier(),
            )
            : [];

        $response =
            $deliveryExecution->isReview()
            ? $this->initReview($deliveryExecution, $testSession, $actionParameters)
            : $this->initTest($deliveryExecution, $testSession, $actionParameters);
        $response['values'] = array_merge(
            $response['values'],
            $dynamicItemData,
        );

        return $response;
    }

    private function initTest(
        DeliveryExecution $deliveryExecution,
        AssessmentTestSession $testSession,
        array $actionParameters,
    ): array {
        if (!in_array($testSession->getState(), self::ASSESSMENT_TEST_SESSION_STATUSES, true)) {
            $this->auditDeliveryExecutionLogger->error(
                sprintf(
                    '[%s] - test taker was not able to initialize the test, state: `%s` and status: `%s` ',
                    $deliveryExecution->getId(),
                    $testSession->getState(),
                    $deliveryExecution->getStatus(),
                ),
            );
            throw new ConflictException('Init action can not be started, the test session or delivery execution current status is not as expected');
        }

        $currentState = $testSession->getCurrentAssessmentItemSession()->getState();
        if ($currentState === AssessmentItemSessionState::MODAL_FEEDBACK) {
            $testSession->getCurrentAssessmentItemSession()->getItemSessionControl()->setShowFeedback(false);
            $testSession->getCurrentAssessmentItemSession()->endAttempt();

            if ($testSession->getCurrentAssessmentItemSession()->getState() === AssessmentItemSessionState::MODAL_FEEDBACK) {
                $testSession->beginAttempt(true);
            }
            $this->deliveryExecutionPropertyService->persistTestSession($testSession);
        }


        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - test taker has initialized the test: %s',
                $deliveryExecution->getId(),
                $testSession->getAssessmentTest()->getIdentifier(),
            ),
        );

        $timer = $this->timerService->beginServerTimer($deliveryExecution, $testSession);
        $testMap = $this->testMapGenerator->generate($testSession, $deliveryExecution);

        $this->eventDispatcher->dispatch(
            new TestSessionInteractionEvent(
                self::class,
                $this->getActionName(),
                $deliveryExecution,
                $testSession,
                $testMap,
            ),
        );

        return $this->getActionProcessorResponse($actionParameters, [
            'testContext' => $this->testContextGenerator->generate($testSession, $deliveryExecution),
            'testMap' => $testMap,
            'timer' => $timer,
            // TODO Investigate when we receive this 'lastStoreId' and if we need to store it.
        ]);
    }

    private function initReview(
        DeliveryExecution $deliveryExecution,
        AssessmentTestSession $testSession,
        array $actionParameters,
    ): array {
        $testSession->getRoute()->rewind();
        $testSession->beginTestSession();
        if (
            $this->ltiCustomSettings->isItemLaunchEnabled()
            && !$this->testSessionNavigator->navigateToItemRef(
                $deliveryExecution,
                $this->ltiCustomSettings->getItemLaunch(),
            )
            && $this->ltiCustomSettings->getNavigationMode() !== 'none'
        ) {
            throw new RequestValidationException(
                sprintf(
                    '[IRRECOVERABLE] Unable to find the item identifier %s to reach provided in LTI custom claims',
                    $this->ltiCustomSettings->getItemLaunch(),
                ),
            );
        }

        return $this->getActionProcessorResponse($actionParameters, [
            'testContext' => $this->testContextGenerator->generate(
                $testSession,
                $deliveryExecution,
                $this->ltiCustomSettings->getItemLaunch(),
            ),
            'testMap' => $this->testMapGenerator->generate($testSession, $deliveryExecution, true),
        ]);
    }
}
