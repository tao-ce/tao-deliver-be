<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

use OAT\Library\TaoTimerClient\Model\Contract\TimerDefinitionInterface;
use OAT\Library\TaoTimerClient\Model\TimerDefinition;
use OAT\Library\TaoTimerClient\Model\TimerDetail;
use stdClass;

trait ExternalTimerDefinitionTrait
{
    private ?TimerDefinitionInterface $externalTimerDefinition = null;

    protected function fromArrayTimerDefinition(array $compressExternalTimerData): ?TimerDefinitionInterface
    {
        if (empty($compressExternalTimerData['externalTimerDefinition'])) {
            return null;
        }

        if ($compressExternalTimerData['externalTimerDefinition'] instanceof TimerDefinitionInterface) {
            return $compressExternalTimerData['externalTimerDefinition'];
        }

        $compressExternalTimerData = $compressExternalTimerData['externalTimerDefinition'];

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
            $testDetail->setMinTime($testData->minTime ?? 0);
            $testDetail->setMaxTime($testData->maxTime);
            $testDetail->setMaxTimeRemaining($testData->maxTimeRemaining);
            $testDetail->setIsStarted((bool)$testData->started);

            $timerDefinition->setTest($testDetail);
        }

        $timerDetailList = [
            'testParts' => 'setTestParts',
            'sections' => 'setSections',
            'items' => 'setItems',
            'extra' => 'setExtra',
        ];

        foreach ($timerDetailList as $key => $setter) {
            if (empty($externalTimerData[$key])) {
                continue;
            }

            $list = [];
            if (is_array($externalTimerData[$key])) {
                foreach ($externalTimerData[$key] as $data) {
                    $def = $this->createTimerDetail($data);
                    $list[] = $def;
                }
                $timerDefinition->$setter(...$list);
            } else {
                $def = $this->createTimerDetail($externalTimerData[$key]);
                $timerDefinition->$setter($def);
            }
        }

        return $timerDefinition;
    }

    protected function toArrayTimerDefinition(): array
    {
        return [
            'externalTimerData' => $this->externalTimerDefinition ? (string)$this->externalTimerDefinition : null,
        ];
    }

    public function withNoExternalTimerData(): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->externalTimerDefinition = null;

        return $deliveryExecutionExtraStateData;
    }

    public function withExternalTimerData(TimerDefinitionInterface $timerDefinition): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->externalTimerDefinition = $timerDefinition;

        return $deliveryExecutionExtraStateData;
    }

    public function getExternalTimerDefinition(): ?TimerDefinitionInterface
    {
        return $this->externalTimerDefinition;
    }

    private function createTimerDetail(stdClass $data): TimerDetail
    {
        $timerDetail = new TimerDetail();
        $timerDetail->setId((string)$data->id);
        $timerDetail->setMaxTime($data->maxTime);
        $timerDetail->setMaxTimeRemaining($data->maxTimeRemaining);
        $timerDetail->setIsStarted($data->started);

        return $timerDetail;
    }
}
