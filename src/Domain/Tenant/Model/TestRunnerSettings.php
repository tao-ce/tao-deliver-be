<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Delivery\Model\Delivery;
use OAT\Library\TenantManagement\Model\TestRunnerThemeInterface;

class TestRunnerSettings
{
    private readonly TestRunnerThemeInterface $theme;

    public function __construct(
        private readonly Delivery $delivery,
        private readonly array $configuration,
        ?TestRunnerThemeInterface $theme,
    ) {
        $this->theme = $theme ?? new EmptyTestRunnerTheme();
    }

    public function getDelivery(): Delivery
    {
        return $this->delivery;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function getTheme(): TestRunnerThemeInterface
    {
        return $this->theme;
    }
}
