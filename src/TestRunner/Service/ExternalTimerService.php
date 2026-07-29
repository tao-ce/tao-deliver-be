<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Service;

use Exception;
use qtism\data\QtiComponent;
use Psr\Log\LoggerInterface;
use qtism\runtime\tests\RouteItem;
use qtism\runtime\tests\AssessmentTestSession;
use OAT\Library\TaoTimerClient\Model\TimerDetail;
use OAT\Library\TaoTimerClient\Model\TimerDefinition;
use OAT\Library\TaoTimerClient\Client\GetTimerException;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use OAT\Library\TaoTimerClient\Client\DeleteTimerException;
use OAT\Library\TaoTimerClient\Service\TimerServiceInterface;
use OAT\Library\TaoTimerClient\Model\Contract\TimerDefinitionInterface;

readonly class ExternalTimerService
{
    public function __construct(
        private LoggerInterface $logger,
        private TimerServiceInterface $timerServiceClient,
        private RealTimeService $realTimeService,
    ) {
    }

    public function getServerTimer(DeliveryExecution $deliveryExecution): ?TimerDefinitionInterface
    {
        if (!$this->isEnabled($deliveryExecution)) {
            return null;
        }

        // if timer was created then no actions
        try {
            return $this->timerServiceClient->getTimer($deliveryExecution->getId());
        } catch (GetTimerException) {
            // no timer setup all good move forward
        }

        return $deliveryExecution->getExtraStateData()->getExternalTimerDefinition();
    }

    public function fetchOrCreateRemoteTimer(
        DeliveryExecution $deliveryExecution,
        AssessmentTestSession $testSession,
    ): ?TimerDefinitionInterface {
        if (!$this->isEnabled($deliveryExecution)) {
            return null;
        }
        if (
            $deliveryExecution->getExtraStateData()->hasTimer() === null
            || $deliveryExecution->getExtraStateData()->getExternalTimerDefinition() !== null
        ) {
            return $this->createRemoteTimer($testSession, $deliveryExecution);
        }

        return $this->getServerTimer($deliveryExecution);
    }

    public function deleteServerTimer(string $deliveryExecutionId): void
    {
        try {
            $this->timerServiceClient->deleteTimer($deliveryExecutionId);
        } catch (GetTimerException) {
            $this->logger->debug(sprintf('No Timer was setup for delivery execution %s', $deliveryExecutionId));
        } catch (DeleteTimerException $exception) {
            $this->logger->warning(
                sprintf(
                    'Timer cannot be removed for deliveryExecutionId [ %s ], reason: %s',
                    $deliveryExecutionId,
                    $exception->getMessage(),
                ),
                compact('exception'),
            );
        } catch (Exception $exception) {
            $this->logger->warning(
                sprintf(
                    'Unexpected error deleting timer for deliveryExecutionId [ %s ], reason: %s',
                    $deliveryExecutionId,
                    $exception->getMessage(),
                ),
                compact('exception'),
            );
        }
    }

    private function createRemoteTimer(
        AssessmentTestSession $testSession,
        DeliveryExecution $deliveryExecution,
    ): ?TimerDefinition {
        $timerDefinition = $deliveryExecution->getExtraStateData()->getExternalTimerDefinition()
            ?? $this->createTimerDefinition($testSession);
        $hasTimer = !$this->isTimerDefinitionEmpty($timerDefinition);
        if ($hasTimer) {
            $this->logger->debug(sprintf('Creating external timer for %s', $deliveryExecution->getId()));
            try {
                $this->timerServiceClient->createTimer($deliveryExecution->getId(), $timerDefinition);
            } catch (Exception $exception) {
                $this->logger->critical('Failed to create a Timer', compact('exception'));
            }
        }
        if (!isset($exception)) {
            $deliveryExecution->addIsTimerEnabledState($hasTimer);
        }
        return $hasTimer ? $timerDefinition : null;
    }

    private function createTimerDefinition(AssessmentTestSession $testSession): TimerDefinition
    {
        $timerDefinition = new TimerDefinition();
        foreach ($testSession->getRoute()->getAllRouteItems() as $routeItem) {
            $this->fillTimerDefinitionByRouteItem($timerDefinition, $routeItem);
        }
        return $timerDefinition;
    }

    private function fillTimerDefinitionByRouteItem(
        TimerDefinitionInterface $timerDefinition,
        RouteItem $routeItem,
    ): void {
        if (!$timerDefinition->getTest()) {
            $timerDefinition->setTest(
                $this->getTimerAssessmentTest($routeItem),
            );
        }

        $timerDefinition->setTestParts(
            ...$this->mergeTimeDetailList(
                $timerDefinition->getTestParts() ?? [],
                [$this->getTimerTestPart($routeItem)],
            ),
        );

        $timerDefinition->setSections(
            ...$this->mergeTimeDetailList(
                $timerDefinition->getSections() ?? [],
                $this->getSectionTimerDetail($routeItem),
            ),
        );

        $timerDefinition->setItems(
            ...$this->mergeTimeDetailList(
                $timerDefinition->getItems() ?? [],
                [$this->getItemTimerDetail($routeItem)],
            ),
        );
    }

    private function isEnabled(DeliveryExecution $deliveryExecution): bool
    {
        return $this->realTimeService->isEnabled()
            && !$deliveryExecution->isReview()
            && $deliveryExecution->getExtraStateData()->hasTimer() !== false;
    }

    private function getTimerAssessmentTest(RouteItem $routeItem): ?TimerDetail
    {
        return $this->createTimerDetailByTimeConstraint(
            $routeItem->getAssessmentTest(),
        );
    }

    private function getTimerTestPart(RouteItem $routeItem): ?TimerDetail
    {
        return $this->createTimerDetailByTimeConstraint(
            $routeItem->getTestPart(),
        );
    }

    private function getSectionTimerDetail(RouteItem $routeItem): array
    {
        $timerDetailList = [];
        $sectionList = $routeItem->getAssessmentSections();

        foreach ($sectionList as $section) {
            $timerDetailList[$section->getIdentifier()] = $this->createTimerDetailByTimeConstraint($section);
        }

        return array_values($timerDetailList);
    }

    private function getItemTimerDetail(RouteItem $routeItem): ?TimerDetail
    {
        return $this->createTimerDetailByTimeConstraint(
            $routeItem->getAssessmentItemRef(),
        );
    }

    private function createTimerDetailByTimeConstraint(QtiComponent $elm): ?TimerDetail
    {
        if (!$elm->getTimeLimits()?->hasMaxTime()) {
            return null;
        }

        $timerDetail = new TimerDetail();

        if ($elm->getTimeLimits()?->hasMinTime()) {
            $timerDetail->setMinTimeInSeconds($elm->getTimeLimits()->getMinTime()->getSeconds(true));
        }
        $timerDetail->setMaxTimeInSeconds($elm->getTimeLimits()->getMaxTime()->getSeconds(true));
        $timerDetail->setId($elm->getIdentifier());

        return $timerDetail;
    }

    private function mergeTimeDetailList(array $oldRecords, array $newRecords): array
    {
        return array_diff(
            array_unique(
                array_merge(
                    $oldRecords ?? [],
                    $newRecords,
                ),
            ),
            [null],
        );
    }

    private function isTimerDefinitionEmpty(TimerDefinition $timerDefinition): bool
    {
        return !$timerDefinition->getTest()
            && !$timerDefinition->getItems()
            && !$timerDefinition->getTestParts()
            && !$timerDefinition->getSections();
    }
}
