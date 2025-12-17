<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Qti\Extractor\QtiResultExtractor;
use App\Qti\Service\AssessmentResultService;
use JetBrains\PhpStorm\ArrayShape;
use qtism\common\datatypes\QtiFloat;
use qtism\data\results\ResultOutcomeVariable;
use qtism\data\storage\xml\XmlStorageException;
use qtism\runtime\common\ResponseVariable;
use qtism\runtime\common\VariableCollection;

class ExtractDeliveryExecutionResultService
{
    public function __construct(
        private readonly QtiResultExtractor $resultExtractor,
        private readonly AssessmentResultService $assessmentResultService,
    ) {
    }

    /**
     * @throws XmlStorageException
     */
    #[ArrayShape([
        'score' => 'float',
        'maxScore' => 'float',
        'totalScore' => 'float',
        'deliveryExecutionId' => 'string',
    ])]
    public function extract(DeliveryExecution $deliveryExecution): array
    {
        $deliveryExecution->restoreOriginalSession();
        $resultXml = $this->extractResultXml($deliveryExecution);
        $scores = $this->extractScores($deliveryExecution);
        $this->assessmentResultService->persist(
            $deliveryExecution->getId(),
            $resultXml,
        );
        if ($deliveryExecution->isSnapshot()) {
            $this->assessmentResultService->persist(
                $deliveryExecution->getOriginalId(),
                $resultXml,
            );
        }

        return [
            'score' => $scores['score'],
            'maxScore' => $scores['maxScore'],
            'totalScore' => $scores['totalScore'],
            'deliveryExecutionId' => $deliveryExecution->getId(),
        ];
    }

    /**
     * @throws XmlStorageException
     */
    public function extractResultXml(DeliveryExecution $deliveryExecution): string
    {
        $resultXml = $this->resultExtractor->extractXmlResultDocument($deliveryExecution)->saveToString();
        if ($deliveryExecution->getResultId() !== $deliveryExecution->getOriginalId()) {
            $this->assessmentResultService->persist(
                "{$deliveryExecution->getTenantId()}/{$deliveryExecution->getResultId()}",
                $resultXml,
            );
        }

        return $resultXml;
    }

    #[ArrayShape(['score' => 'float', 'maxScore' => 'float', 'totalScore' => 'float'])]
    public function extractScores(DeliveryExecution $deliveryExecution): array
    {
        $score = 0.0;
        $maxScore = 0.0;
        $numberOfResults = 0;

        /** @var VariableCollection $result */
        foreach ($this->resultExtractor->extractOutcomeVariables($deliveryExecution) as $result) {
            /** @var ResponseVariable|ResultOutcomeVariable $placeHolder */
            foreach ($result as $placeHolder) {
                if ('SCORE' === $placeHolder->getIdentifier()) {
                    /** @var QtiFloat $value */
                    $value = $placeHolder->getValue();
                    $score += $value->getValue();
                    $numberOfResults++;
                }

                if ('MAXSCORE' === $placeHolder->getIdentifier()) {
                    $value = $placeHolder->getValue();
                    $maxScore += $value?->getValue();
                }

                if ($placeHolder instanceof ResultOutcomeVariable) {
                    if ('SCORE_TOTAL' === $placeHolder->getIdentifier()->getValue()) {
                        $values = $placeHolder->getValues();
                        if (count($values)) {
                            $score = $values[0]->getValue();
                        }
                    }

                    if ('SCORE_TOTAL_MAX' === $placeHolder->getIdentifier()->getValue()) {
                        $values = $placeHolder->getValues();
                        if (count($values)) {
                            $maxScore = $values[0]->getValue();
                        }
                    }

                    if ('SCORE_RATIO' === $placeHolder->getIdentifier()->getValue()) {
                        $values = $placeHolder->getValues();
                        $ratio = $values[0]?->getValue();
                    }
                }
            }
        }

        return [
            'score' => $ratio ?? $this->getCalculatedRatio($score, $maxScore, $numberOfResults),
            'maxScore' => $maxScore,
            'totalScore' => $score,
        ];
    }

    private function getCalculatedRatio(float $score, float $maxScore, int $numberOfResults): float
    {
        if ($maxScore > 0) {
            return $score / $maxScore;
        }

        if ($numberOfResults > 0) {
            return $score / $numberOfResults;
        }

        return $score;
    }
}
