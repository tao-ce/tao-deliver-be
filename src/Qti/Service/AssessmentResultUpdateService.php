<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Qti\Service\Contract\ArgumentAssessmentResultInterface;
use App\Qti\Service\Contract\ArgumentOutcomeVariableInterface;
use App\Qti\Service\Contract\Exceptions\OutcomeVariableParametersMismatch;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use qtism\common\datatypes\QtiScalar;
use qtism\common\enums\BaseType;
use qtism\common\enums\Cardinality;
use qtism\runtime\common\ProcessingException;
use qtism\runtime\common\Utils;
use qtism\runtime\processing\OutcomeProcessingEngine;
use qtism\runtime\tests\AssessmentItemSession;
use qtism\runtime\tests\AssessmentTestSession;

class AssessmentResultUpdateService
{
    public function __construct(
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly DeliveryExecutionService $deliveryExecutionService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param DeliveryExecution $deliveryExecution
     * @param ArgumentAssessmentResultInterface $assessmentResultInput
     * @param array<string, DateTimeInterface> $manuallyGradedItems
     * @return AssessmentTestSession
     */
    public function updateOutcomeVariableOnAssessmentSession(
        DeliveryExecution $deliveryExecution,
        ArgumentAssessmentResultInterface $assessmentResultInput,
        array $manuallyGradedItems = [],
    ): AssessmentTestSession {
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        foreach ($assessmentResultInput->getItemResultAssocList() as $itemId => $itemData) {
            if (isset($manuallyGradedItems[$itemId]) && $itemData->getTimestamp() < $manuallyGradedItems[$itemId]) {
                continue;
            }

            $assessmentItemSessionCollection = $testSession->getAssessmentItemSessions($itemId);
            if (!$assessmentItemSessionCollection || $assessmentItemSessionCollection->isEmpty()) {
                continue;
            }
            /** @var AssessmentItemSession $assessmentItemSession */
            $assessmentItemSession = $assessmentItemSessionCollection->current();

            foreach ($itemData->getOutcomeVariableAssoc() as $itemVariable) {
                try {
                    $this->updateTestVariableValue($assessmentItemSession, $itemVariable);
                } catch (OutcomeVariableParametersMismatch $exception) {
                    $this->logger->warning(
                        "Item $itemId – {$exception->getMessage()}",
                        compact('exception'),
                    );
                }
            }

            $deliveryExecution
                ->markItemAsExternalScored($itemId)
                ->withFinalManuallyGradedItem($itemId, $itemData->getTimestamp());
        }

        foreach ($assessmentResultInput->getTestResult()->outcomeVariableList as $testVariable) {
            try {
                $this->updateTestVariableValue($testSession, $testVariable);
            } catch (OutcomeVariableParametersMismatch $exception) {
                $this->logger->warning(
                    $exception->getMessage(),
                    compact('exception'),
                );
            }
        }

        if ($testSession->getAssessmentTest()->hasOutcomeProcessing()) {
            try {
                $outcomeProcessingEngine = new OutcomeProcessingEngine($testSession->getAssessmentTest()->getOutcomeProcessing(), $testSession);
                $outcomeProcessingEngine->process();
            } catch (ProcessingException $exception) {
                $this->logger->critical($exception->getMessage(), compact('exception'));
            }
        }

        $deliveryExecution->preserveOriginalSession();
        $this->deliveryExecutionPropertyService->persistTestSession($testSession);
        $this->deliveryExecutionService->saveDeliveryExecution($deliveryExecution);
        $this->modifyOriginalDeliveryExecution($deliveryExecution);

        return $testSession;
    }

    private function modifyOriginalDeliveryExecution(DeliveryExecution $deliveryExecution): void
    {
        if ($deliveryExecution->isSnapshot()) {
            return;
        }

        $originalDeliveryExecution = $this->deliveryExecutionService->findDeliveryExecution(
            $deliveryExecution->getOriginalId(),
        );
        if (
            null === $originalDeliveryExecution
            || $originalDeliveryExecution->getFinishedAt() !== $deliveryExecution->getFinishedAt()
        ) {
            return;
        }

        $originalDeliveryExecution->setExtraStateData($deliveryExecution->getExtraStateData());
        $originalDeliveryExecution->setQtiSdkEncodedTestSession($deliveryExecution->getQtiSdkEncodedTestSession());
        $this->deliveryExecutionService->saveDeliveryExecution($originalDeliveryExecution);
    }

    private function getValueModelByArgumentOutcomeVariable(ArgumentOutcomeVariableInterface $outcomeVariable): QtiScalar
    {
        $inputValue = $outcomeVariable->getValue();
        $baseTypeId = BaseType::getConstantByName($outcomeVariable->getBaseType());
        switch ($baseTypeId) {
            case BaseType::FLOAT:
                $inputValue = (float)$inputValue;
                break;
            case BaseType::INTEGER:
                $inputValue = (int)$inputValue;
                break;
        }

        return Utils::valueToRuntime($inputValue, $baseTypeId);
    }

    private function updateTestVariableValue(
        AssessmentTestSession|AssessmentItemSession $session,
        ArgumentOutcomeVariableInterface $outcomeVariable,
    ): void {
        if (!$outcomeVariable->isApplicable()) {
            return;
        }

        $variableValue = $this->getValueModelByArgumentOutcomeVariable($outcomeVariable);
        $sessionOutcomeVariable = $session->getVariable($outcomeVariable->getId());
        if (!$sessionOutcomeVariable) {
            return;
        }

        if (BaseType::getNameByConstant($sessionOutcomeVariable->getBaseType()) !== $outcomeVariable->getBaseType()) {
            throw OutcomeVariableParametersMismatch::createForBaseTypeMismatch(
                $sessionOutcomeVariable,
                $outcomeVariable,
            );
        }
        if (
            Cardinality::getNameByConstant($sessionOutcomeVariable->getCardinality())
            !== $outcomeVariable->getCardinality()
        ) {
            throw OutcomeVariableParametersMismatch::createForCardinalityMismatch(
                $sessionOutcomeVariable,
                $outcomeVariable,
            );
        }

        $sessionOutcomeVariable->setValue($variableValue);
    }
}
