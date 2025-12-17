<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Normalizer;

use qtism\common\datatypes\QtiDuration;
use qtism\data\AssessmentItemRef;
use qtism\data\AssessmentSection;
use qtism\data\AssessmentTest;
use qtism\data\TestPart;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\TimeConstraint;
use qtism\runtime\tests\TimeConstraintCollection;

class TimeConstraintNormalizer
{
    public function normalize(AssessmentTestSession $testSession, TimeConstraint $timeConstraint): ?array
    {
        if (!$testSession->isRunning()) {
            return null;
        }

        /** @var AssessmentTest|TestPart|AssessmentSection|AssessmentItemRef $source */
        $source = $timeConstraint->getSource();
        $identifier = $source->getIdentifier();
        $timeLimits = $source->getTimeLimits();

        $minTime = $timeLimits !== null && $timeLimits->hasMinTime() ? $timeLimits->getMinTime()->getSeconds(true) : false;
        $maxTime = $timeLimits !== null && $timeLimits->hasMaxTime() ? $timeLimits->getMaxTime()->getSeconds(true) : false;

        return [
            'allowLateSubmission' => $timeConstraint->allowLateSubmission(),
            'label' => method_exists($source, 'getTitle')
                ? $source->getTitle()
                : $identifier,
            'maxTime' => $maxTime,
            'maxTimeRemaining' => $timeConstraint->getMaximumRemainingTime() instanceof QtiDuration
                ? $timeConstraint->getMaximumRemainingTime()->getSeconds(true)
                : false,
            'minTime' => $minTime,
            'minTimeRemaining' => $timeConstraint->getMinimumRemainingTime() instanceof QtiDuration
                ? $timeConstraint->getMinimumRemainingTime()->getSeconds(true)
                : false,
            'qtiClassName' => $source->getQtiClassName(),
            'source' => $identifier,
            'extraTime' => [
                'total' => 0,
                'consumed' => 0,
                'remaining' => 0,
            ],
        ];
    }

    public function normalizeCollection(
        AssessmentTestSession $testSession,
        ?TimeConstraintCollection $timeConstraintCollection = null,
    ): array {
        if (!$testSession->isRunning()) {
            return [];
        }

        if ($timeConstraintCollection === null) {
            $timeConstraintCollection = $testSession->getTimeConstraints() ?: new TimeConstraintCollection([]);
        }

        $timeConstraints = array_map(function (TimeConstraint $timeConstraint) use ($testSession) {
            return $this->normalize($testSession, $timeConstraint);
        }, $timeConstraintCollection->getArrayCopy());

        return array_filter($timeConstraints, static function (array $normalizedTimeConstraint) {
            return $normalizedTimeConstraint['maxTime'] !== false
                || $normalizedTimeConstraint['minTime'] !== false
                || $normalizedTimeConstraint['maxTimeRemaining'] !== false
                || $normalizedTimeConstraint['minTimeRemaining'] !== false;
        });
    }
}
