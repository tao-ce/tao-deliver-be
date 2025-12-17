<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData;

use App\Domain\DeliveryExecution\Model\ExtraStateData\DurationStorage\DurationInterface;
use App\Domain\DeliveryExecution\Model\ExtraStateData\DurationStorage\ServerDuration;
use LogicException;

class DurationStorage
{
    /** @var ServerDuration[] */
    private $serverDurations;

    public function __construct(array $serverDurations = [])
    {
        $this->serverDurations = $serverDurations;
    }

    /**
     * @return ServerDuration[]
     */
    public function getServerDurations(): array
    {
        return $this->serverDurations;
    }

    public function withStartedServerTimer(string $qtiComponentIdentifier): self
    {
        $durationStorage = clone $this;
        $durationStorage->serverDurations[] = new ServerDuration($qtiComponentIdentifier, $this->getCurrentTimestamp());

        return $durationStorage;
    }

    /**
     * @throws LogicException
     */
    public function withStoppedServerTimer(string $qtiComponentIdentifier): self
    {
        $serverDurationsForQtiComponent = array_filter(
            $this->serverDurations,
            static function (ServerDuration $serverDuration) use ($qtiComponentIdentifier) {
                return $serverDuration->getQtiComponentIdentifier() === $qtiComponentIdentifier;
            },
        );

        if (count($serverDurationsForQtiComponent) === 0) {
            throw new LogicException(sprintf(
                'Cannot end server timer as it was not started yet: %s',
                $qtiComponentIdentifier,
            ));
        }

        /** @var ServerDuration $lastServerDuration */
        $lastServerDuration      = end($serverDurationsForQtiComponent);
        $lastServerDurationIndex = key($serverDurationsForQtiComponent);

        if ($lastServerDuration->isEnded()) {
            throw new LogicException(sprintf(
                'Cannot end server timer as it ended already: %s',
                $qtiComponentIdentifier,
            ));
        }

        $durationStorage = clone $this;
        $durationStorage->serverDurations[$lastServerDurationIndex]
            = $lastServerDuration->withEndedAt($this->getCurrentTimestamp());

        return $durationStorage;
    }

    public function getServerDuration(string $qtiComponentIdentifier): float
    {
        return $this->getDurationSum($this->serverDurations, $qtiComponentIdentifier);
    }

    public function withClearedDurations(): self
    {
        $this->serverDurations = [];

        return $this;
    }

    /**
     * @param DurationInterface[] $durations
     */
    private function getDurationSum(array $durations, string $qtiComponentIdentifier): float
    {
        $durationsForQtiComponent = array_filter(
            $durations,
            static function (DurationInterface $duration) use ($qtiComponentIdentifier) {
                return $duration->getQtiComponentIdentifier() === $qtiComponentIdentifier;
            },
        );

        $durationValues = array_map(static function (DurationInterface $duration) {
            return $duration->getDuration();
        }, $durationsForQtiComponent);

        return array_sum($durationValues);
    }

    private function getCurrentTimestamp(): float
    {
        return round(microtime(true), 6);
    }
}
