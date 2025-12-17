<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\DataStore\Sender\DataStoreSenderInterface;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\ExtraStateData\OverallComment;
use App\Generator\UuidGenerator;
use App\Lti\Sender\LtiBasicOutcomeSender;
use App\Messenger\Message\DeliveryExecutionItemExternalScoringMessage;
use App\Messenger\Message\ResultExtractionMessage;
use App\Qti\Service\AssessmentResultUpdateService;
use App\Qti\Service\Contract\ArgumentItemResultInterface;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use App\Service\DeliveryExecution\ExtractDeliveryExecutionResultService;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ItemExternalScoringHandler
{
    public function __construct(
        private AssessmentResultUpdateService $assessmentResultUpdateService,
        private DeliveryExecutionService $deliveryExecutionService,
        private ExtractDeliveryExecutionResultService $extractDeliveryExecutionResultService,
        private LoggerInterface $auditPlatformLogger,
        private LtiBasicOutcomeSender $basicOutcomeSender,
        private UuidGenerator $uuidGenerator,
        private DataStoreSenderInterface $dataStoreSender,
    ) {
    }

    public function __invoke(DeliveryExecutionItemExternalScoringMessage $message): void
    {
        $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecution($message->getContextSourcedId());

        if (!$deliveryExecution) {
            $this->auditPlatformLogger->info(
                sprintf('[%s] Delivery execution was not found', $message->getContextSourcedId()),
            );
            return;
        }

        $this->updateItemReviewComments($message->getItemResultAssocList(), $deliveryExecution);

        if ($message->getInitialResult() !== null) {
            $initiallyGradedDeliveryExecution = $deliveryExecution->initializeInitiallyScoredQtiSdkEncodedTestSession();
            $this->assessmentResultUpdateService->updateOutcomeVariableOnAssessmentSession(
                $initiallyGradedDeliveryExecution,
                $message->getInitialResult(),
                $deliveryExecution->getExtraStateData()->getInitialManuallyGradedItems(),
            );
            foreach (
                $initiallyGradedDeliveryExecution
                    ->getExtraStateData()
                    ->getFinalManuallyGradedItems() as $itemId => $timestamp
            ) {
                $deliveryExecution->withInitialManuallyGradedItem($itemId, $timestamp);
            }
        }
        $this->assessmentResultUpdateService->updateOutcomeVariableOnAssessmentSession(
            $deliveryExecution,
            $message,
            $deliveryExecution->getExtraStateData()->getFinalManuallyGradedItems(),
        );

        $this->extractDeliveryExecutionResultService->extractResultXml($deliveryExecution);
        $scores = $this->extractDeliveryExecutionResultService->extractScores($deliveryExecution);
        $this->auditPlatformLogger->info(
            sprintf('[%s] Result/Scoring was extracted and processed', $deliveryExecution->getId()),
        );

        $this->basicOutcomeSender->send(
            $deliveryExecution,
            $scores,
            new ResultExtractionMessage(
                $this->uuidGenerator->generate(),
                $deliveryExecution->getId(),
            ),
        );

        $this->dataStoreSender->send($deliveryExecution);
    }

    /**
     * @param ArgumentItemResultInterface[] $itemReviewComments
     */
    private function updateItemReviewComments(array $itemReviewComments, DeliveryExecution $deliveryExecution): void
    {
        foreach ($itemReviewComments as $itemId => $itemResult) {
            $overallComment = $itemResult->getOverallComment();
            $existingOverallComment = $deliveryExecution->getItemOverallComment($itemId);

            if (($overallComment['timestamp'] ?? 0) > $existingOverallComment?->timestamp) {
                try {
                    $deliveryExecution->withItemOverallComment(
                        $itemId,
                        OverallComment::fromArray($overallComment),
                    );
                } catch (InvalidArgumentException $exception) {
                    $this->auditPlatformLogger->critical(
                        "Overall comment on $itemId failed to be recorded: {$exception->getMessage()}",
                        compact('exception'),
                    );
                }
            }
        }
    }
}
