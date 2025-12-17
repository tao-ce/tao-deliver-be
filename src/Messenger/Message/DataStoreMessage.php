<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message;

/**
* @deprecated
*/
class DataStoreMessage extends AbstractDeliveryExecutionAwareMessage implements
    BatteryAwareMessageInterface,
    ManuallyScoredItemAwareMessageInterface
{
    private ?string $batteryId = null;
    private ?array $manuallyScoredItemIds = null;
    private ?string $invalidatedBy = null;
    private ?\DateTimeInterface $invalidatedAt = null;
    private bool $isResultInvalidated = false;

    public function getBatteryId(): ?string
    {
        return $this->batteryId;
    }

    public function setBatteryId(?string $batteryId): static
    {
        $this->batteryId = $batteryId;

        return $this;
    }

    public function setManuallyScoredItemIds(?array $manuallyScoredItemIds): void
    {
        $this->manuallyScoredItemIds = $manuallyScoredItemIds;
    }

    public function getManuallyScoredItemIds(): ?array
    {
        return $this->manuallyScoredItemIds;
    }

    public function getInvalidatedBy(): ?string
    {
        return $this->invalidatedBy;
    }

    public function setInvalidatedBy(?string $invalidatedBy): static
    {
        $this->invalidatedBy = $invalidatedBy;

        return $this;
    }

    public function getInvalidatedAt(): ?\DateTimeInterface
    {
        return $this->invalidatedAt;
    }

    public function setInvalidatedAt(?\DateTimeInterface $invalidatedAt): static
    {
        $this->invalidatedAt = $invalidatedAt;

        return $this;
    }

    public function isResultInvalidated(): bool
    {
        return $this->isResultInvalidated;
    }

    public function setIsResultInvalidated(bool $isResultInvalidated): static
    {
        $this->isResultInvalidated = $isResultInvalidated;

        return $this;
    }
}
