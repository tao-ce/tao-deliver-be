<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

trait MultiLanguageOptionTrait
{
    private ?string $mainLocale = null;
    private bool $isMultilanguage = false;

    public function getMainLocale(): ?string
    {
        return $this->mainLocale;
    }

    public function withMainLocale(string $mainLocale): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->mainLocale = $mainLocale;

        return $deliveryExecutionExtraStateData;
    }

    public function isMultilanguage(): bool
    {
        return $this->isMultilanguage;
    }

    public function withMultiLanguage(): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $deliveryExecutionExtraStateData->isMultilanguage = true;

        return $deliveryExecutionExtraStateData;
    }
}
