<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Event\TestSessionInteractionEvent;
use Psr\Log\LoggerInterface;
use qtism\runtime\tests\AssessmentTestSession;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class FlagItemActionProcessor extends AbstractActionProcessor
{
    public const ACTION_NAME = 'flagItem';

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
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

        $itemIdentifier = $this->getItemIdentifier($testSession, $actionParameters['parameters']['position']);

        $flag = $actionParameters['parameters']['flag'];

        if (false === $flag) {
            $deliveryExecution->unFlagItem($itemIdentifier);

            $this->auditDeliveryExecutionLogger->info(
                sprintf(
                    '[%s] - test taker changed the state of Item: [%s] to un-flagged',
                    $deliveryExecution->getId(),
                    $itemIdentifier,
                ),
            );
        } elseif ($flag) {
            $deliveryExecution->flagItem($itemIdentifier);

            $this->auditDeliveryExecutionLogger->info(
                sprintf(
                    '[%s] - test taker changed the state of Item: [%s] to flagged',
                    $deliveryExecution->getId(),
                    $itemIdentifier,
                ),
            );
        }

        $this->eventDispatcher->dispatch(
            new TestSessionInteractionEvent(self::class, $this->getActionName(), $deliveryExecution, $testSession),
        );

        return $this->getActionProcessorResponse($actionParameters, []);
    }

    private function getItemIdentifier(AssessmentTestSession $testSession, string $position): string
    {
        $assessmentItemRefsArray = $testSession->getRoute()->getAssessmentItemRefs()->getArrayCopy(true);

        $itemIdentifiersArray = array_map(
            function ($row) {
                return $row->getIdentifier();
            },
            $assessmentItemRefsArray,
        );

        return array_values($itemIdentifiersArray)[$position];
    }
}
