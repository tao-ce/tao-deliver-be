<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

use App\Domain\DeliveryExecution\Model\ExtraStateData\DurationStorage;
use App\Domain\DeliveryExecution\Model\ExtraStateData\DurationStorage\ServerDuration;

trait DurationStorageTrait
{
    private ?DurationStorage $durationStorage = null;

    protected function fromArrayDurationStorage(array $extraStateData): DurationStorage
    {
        if (!isset($extraStateData['durationStorage'])) {
            return new DurationStorage();
        }

        if ($extraStateData['durationStorage'] instanceof DurationStorage) {
            return $extraStateData['durationStorage'];
        }

        $durationStorage = $extraStateData['durationStorage'];

        $serverDurations = array_map(static function (array $serverDurationData) {
            return new ServerDuration(
                $serverDurationData['qtiComponentIdentifier'],
                $serverDurationData['startedAt'],
                $serverDurationData['endedAt'],
            );
        }, $durationStorage['serverDurations']);

        return new DurationStorage($serverDurations);
    }

    protected function toArrayDurationStorage(): array
    {
        return [
            'clientDurations' => [], // keeping this for data compatibility in case we'd be pulling the release back
            'serverDurations' => array_map(static function (ServerDuration $serverDuration) {
                return [
                    'qtiComponentIdentifier' => $serverDuration->getQtiComponentIdentifier(),
                    'startedAt' => $serverDuration->getStartedAt(),
                    'endedAt' => $serverDuration->getEndedAt(),
                ];
            }, $this->getDurationStorage()->getServerDurations()),
        ];
    }
    public function withDurationStorage(DurationStorage $durationStorage): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->durationStorage = $durationStorage;

        return $deliveryExecutionExtraStateData;
    }

    public function getDurationStorage(): DurationStorage
    {
        if (!$this->durationStorage) {
            $this->durationStorage = new DurationStorage();
        }
        return $this->durationStorage;
    }
}
