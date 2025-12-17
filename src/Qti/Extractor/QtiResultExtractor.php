<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Extractor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\Asset\AssessmentResultUploadedFilesReplacer;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use qtism\data\results\AssessmentResult;
use qtism\data\results\ResultOutcomeVariable;
use qtism\data\storage\xml\XmlResultDocument;
use qtism\runtime\results\AssessmentResultBuilder;
use qtism\runtime\tests\RouteItem;

class QtiResultExtractor
{
    private const REQUIRED_OUTCOME_VARIABLES = [
        'SCORE_TOTAL',
        'SCORE_TOTAL_MAX',
        'SCORE_RATIO',
    ];

    public function __construct(
        private readonly DeliveryExecutionPropertyService $deliveryExecutionPropertyService,
        private readonly AssessmentResultUploadedFilesReplacer $uploadedFilesReplacer,
        private readonly iterable $preProcessors = [],
    ) {
    }

    public function extractVariables(DeliveryExecution $deliveryExecution): array
    {
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $allRouteItems = $testSession->getRoute()->getAllRouteItems();
        $results = [];

        /** @var RouteItem $routeItem */
        foreach ($allRouteItems as $routeItem) {
            $itemRef = $routeItem->getAssessmentItemRef();
            $occurrence = $routeItem->getOccurence();
            $itemSession = $testSession->getAssessmentItemSessionStore()->getAssessmentItemSession($itemRef, $occurrence);

            $results[$routeItem->getAssessmentItemRef()->getIdentifier()] = $itemSession->getAllVariables();
        }

        return $results;
    }

    public function extractOutcomeVariables(DeliveryExecution $deliveryExecution): iterable
    {
        $assessmentResult = $this->getAssessmentResult($deliveryExecution)->getTestResult();

        $requiredOutcomeVariables = array_flip(self::REQUIRED_OUTCOME_VARIABLES);

        /** @var ResultOutcomeVariable $outcomeVariable */
        foreach ($assessmentResult->getItemVariables() as $outcomeVariable) {
            $variableIdentifier = (string)$outcomeVariable->getIdentifier();

            if (isset($requiredOutcomeVariables[$variableIdentifier]) && !count($outcomeVariable->getValues())) {
                break;
            }

            unset($requiredOutcomeVariables[$variableIdentifier]);
        }

        return $requiredOutcomeVariables
            ? $this->extractVariables($deliveryExecution)
            : [$assessmentResult->getItemVariables()];
    }

    public function extractXmlResultDocument(DeliveryExecution $deliveryExecution): XmlResultDocument
    {
        $assessmentResult = $this->getAssessmentResult($deliveryExecution);
        $this->uploadedFilesReplacer->replace($assessmentResult);

        $xmlResultDocument = new XmlResultDocument();
        $xmlResultDocument->setDocumentComponent($assessmentResult);

        return $xmlResultDocument;
    }

    private function getAssessmentResult(DeliveryExecution $deliveryExecution): AssessmentResult
    {
        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        foreach ($this->preProcessors as $preProcessor) {
            $preProcessor->process($deliveryExecution, $testSession);
        }

        $resultBuilder = new AssessmentResultBuilder($testSession);

        return $resultBuilder->buildResult();
    }
}
