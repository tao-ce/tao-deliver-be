<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\DurationStorage;

class ServerDuration implements DurationInterface
{
    /** @var string */
    private $qtiComponentIdentifier;

    /** @var float */
    private $startedAt;

    /** @var float|null */
    private $endedAt;

    public function __construct(string $qtiComponentIdentifier, float $startedAt, ?float $endedAt = null)
    {
        $this->qtiComponentIdentifier = $qtiComponentIdentifier;
        $this->startedAt = $startedAt;
        $this->endedAt = $endedAt;
    }

    public function getQtiComponentIdentifier(): string
    {
        return $this->qtiComponentIdentifier;
    }

    public function getStartedAt(): float
    {
        return $this->startedAt;
    }

    public function getEndedAt(): ?float
    {
        return $this->endedAt;
    }

    public function withEndedAt(float $endedAt): self
    {
        $serverDuration = clone $this;
        $serverDuration->endedAt = $endedAt;

        return $serverDuration;
    }

    public function getDuration(): float
    {
        if ($this->endedAt === null) {
            return 0.0;
        }

        return $this->endedAt - $this->startedAt;
    }

    public function isEnded(): bool
    {
        return $this->endedAt !== null;
    }
}
