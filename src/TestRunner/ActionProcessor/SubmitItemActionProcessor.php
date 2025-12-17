<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Event\TestSessionInteractionEvent;
use App\TestRunner\Service\ItemSessionService;
use League\Flysystem\FilesystemReader;
use Psr\Log\LoggerInterface;
use qtism\data\NavigationMode;
use qtism\data\SubmissionMode;
use qtism\runtime\common\Variable;
use qtism\runtime\pci\json\Marshaller;
use qtism\runtime\pci\json\MarshallingException;
use qtism\runtime\rules\ProcessingCollectionException;
use qtism\runtime\tests\AssessmentItemSessionState;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\AssessmentTestSessionException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SubmitItemActionProcessor extends AbstractActionProcessor
{
    public const ACTION_NAME = 'submitItem';

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly ItemSessionService $itemSessionService,
        private readonly FilesystemReader $qtiCompiledDeliveriesStorage,
        private readonly Marshaller $marshaller,
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
            $deliveryExecution->addTemporaryItemState($itemIdentifier, $itemState);
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
            if (
                $testSession
                ->getCurrentAssessmentItemSession()
                ->getState() === AssessmentItemSessionState::INTERACTING
            ) {
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

        $this->deliveryExecutionPropertyService->persistTestSession($testSession);
        $this->eventDispatcher->dispatch(
            new TestSessionInteractionEvent(self::class, $this->getActionName(), $deliveryExecution, $testSession),
        );

        $displayFeedbacks = $testSession->getCurrentSubmissionMode() !== SubmissionMode::SIMULTANEOUS;

        $response['displayFeedbacks'] = $displayFeedbacks;
        $response['itemSession'] = $displayFeedbacks ? $this->getItemSession($testSession) : null;
        $response['feedbacks'] = $displayFeedbacks
            ? $this->getVariableElements($deliveryExecution, $parameters['itemIdentifier'])
            : null;

        return $this->getActionProcessorResponse($actionParameters, $response);
    }

    private function getVariableElements(DeliveryExecution $deliveryExecution, string $itemRefIdentifier): ?array
    {
        $filePath = $deliveryExecution->getVariableElementsPath($itemRefIdentifier);

        if ($this->qtiCompiledDeliveriesStorage->has($filePath)) {
            $feedbackJson = $this->qtiCompiledDeliveriesStorage->read($filePath);

            return json_decode($feedbackJson, true);
        }

        return null;
    }

    /**
     * @throws MarshallingException
     */
    private function getItemSession(AssessmentTestSession $testSession): array
    {
        $output = [];
        $currentItem = $testSession->getCurrentAssessmentItemRef();
        $currentOccurrence = $testSession->getCurrentAssessmentItemRefOccurence();
        $itemSession = $testSession
            ->getAssessmentItemSessionStore()
            ->getAssessmentItemSession($currentItem, $currentOccurrence);

        /** @var Variable $variable */
        foreach ($itemSession->getAllVariables() as $variable) {
            $output[$variable->getIdentifier()] = $this->marshaller->marshall(
                $variable->getValue(),
                Marshaller::MARSHALL_ARRAY,
            );
        }

        $output['itemAnswered'] = $testSession->getCurrentNavigationMode() === NavigationMode::LINEAR
            ? $itemSession->isPresented()
            : $itemSession->isResponded();

        return $output;
    }
}
