<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Normalizer;

use OAT\Library\TaoTimerClient\Model\Contract\TimerDefinitionInterface;
use OAT\Library\TaoTimerClient\Model\TimerDefinition;
use OAT\Library\TaoTimerClient\Model\TimerDetail;

trait ExternalTimerDefinitionNormalizerTrait
{
    protected function denormalizeExternalTimerDefinition(array $compressExternalTimerData): ?TimerDefinitionInterface
    {
        $externalTimerData = (array)json_decode($compressExternalTimerData['externalTimerData'] ?? '{}');
        if (empty($externalTimerData)) {
            return null;
        }

        $timerDefinition = new TimerDefinition();

        // test
        if (!empty($externalTimerData['test'])) {
            $testData = $externalTimerData['test'];
            $testDetail = new TimerDetail();

            $testDetail->setId((string)$testData->id);
            $testDetail->setMaxTime($testData->maxTimeRemaining);
            $testDetail->setMaxTimeRemaining($testData->maxTimeRemaining);
            $testDetail->setIsStarted((bool)$testData->started);

            $timerDefinition->setTest($testDetail);
        }


        $timerDetailList = [
            'testParts' => 'setTestParts',
            'sections' => 'setSections',
            'items' => 'setItems',
        ];

        foreach ($timerDetailList as $key => $setter) {
            if (empty($externalTimerData[$key])) {
                continue;
            }

            $list = [];
            foreach ($externalTimerData[$key] as $data) {
                $def = new TimerDetail();
                $def->setId((string)$data->id);
                $def->setMaxTime($data->maxTimeRemaining);
                $def->setMaxTimeRemaining($data->maxTimeRemaining);
                $def->setIsStarted((bool)$data->started);
                $list[] = $def;
            }
            $timerDefinition->$setter(...$list);
        }

        return $timerDefinition;
    }

    protected function normalizeExternalTimerDefinition(?TimerDefinitionInterface $timerDefinition): array
    {
        return [
            'externalTimerData' => $timerDefinition ? (string)$timerDefinition : null,
        ];
    }
}
