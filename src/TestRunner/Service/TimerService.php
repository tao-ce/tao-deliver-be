<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use LogicException;
use OAT\Library\TaoTimerClient\Model\Contract\TimerDefinitionInterface;
use Psr\Log\LoggerInterface;
use qtism\common\datatypes\QtiDuration;
use qtism\data\AssessmentItemRef;
use qtism\data\AssessmentSection;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\RouteItem;

class TimerService
{
    public function __construct(
        private readonly ExternalTimerService $externalTimerService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function beginServerTimer(
        DeliveryExecution $deliveryExecution,
        AssessmentTestSession $testSession,
    ): ?TimerDefinitionInterface {
        $this->startDeliveryExecutionTimer($deliveryExecution, $testSession);
        return $this->externalTimerService->fetchOrCreateRemoteTimer($deliveryExecution, $testSession);
    }

    public function startServerTimer(
        DeliveryExecution $deliveryExecution,
        AssessmentTestSession $testSession,
    ): void {
        $this->startDeliveryExecutionTimer($deliveryExecution, $testSession);
    }
    public function endServerTimer(
        DeliveryExecution $deliveryExecution,
        AssessmentTestSession $testSession,
        float $clientDuration,
    ): void {
        $routeItem = $testSession->getRoute()->current();
        $qtiComponentIdentifier = $routeItem->getAssessmentItemRef()->getIdentifier();

        $this->logger->debug(sprintf('End server timer for %s', $qtiComponentIdentifier));

        try {
            $deliveryExecution->endServerTimer($qtiComponentIdentifier);
        } catch (LogicException $exception) {
            $this->logger->warning(
                sprintf(
                    '[%s] %s',
                    $deliveryExecution->getId(),
                    $exception->getMessage(),
                ),
                compact('exception'),
            );
        }

        foreach ($this->getImmediateQtiComponentsFromRouteItem($routeItem) as $qtiComponent) {
            $qtiComponentIdentifier = $qtiComponent->getIdentifier();

            $this->logger->debug(sprintf(
                'Updating duration of %s with %s seconds in the test session',
                $qtiComponentIdentifier,
                round($clientDuration),
            ));

            // If the QtiComponent is an ItemRef, update the duration in the ItemSession
            // Otherwise update the duration in the DurationStore of the TestSession
            if ($qtiComponent instanceof AssessmentItemRef) {
                $itemSession = $testSession->getAssessmentItemSessionStore()->getAssessmentItemSession(
                    $qtiComponent,
                    $routeItem->getOccurence(),
                );

                $itemSession['duration']
                    ->add(new QtiDuration('PT' . round($clientDuration) . 'S'));
            } else {
                $testSession
                    ->getDurationStore()[$qtiComponentIdentifier]
                    ->add(new QtiDuration('PT' . round($clientDuration) . 'S'));
            }
        }
    }

    private function getImmediateQtiComponentsFromRouteItem(RouteItem $routeItem): iterable
    {
        yield $routeItem->getAssessmentItemRef();
        yield $routeItem->getTestPart();
        yield $routeItem->getAssessmentTest();

        /** @var AssessmentSection $section */
        foreach ($routeItem->getAssessmentSections() as $section) {
            yield $section;
        }
    }
    private function startDeliveryExecutionTimer(
        DeliveryExecution $deliveryExecution,
        AssessmentTestSession $testSession,
    ): void {
        $routeItem = $testSession->getRoute()->current();
        $qtiComponentIdentifier = $routeItem->getAssessmentItemRef()->getIdentifier();

        $this->logger->debug(sprintf('Begin server timer for %s', $qtiComponentIdentifier));

        $deliveryExecution->startServerTimer($qtiComponentIdentifier);
    }
}
