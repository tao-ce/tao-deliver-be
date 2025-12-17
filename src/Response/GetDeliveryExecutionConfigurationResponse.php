<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Response;

use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\Tenant\Model\EmptyTestRunnerTheme;
use OAT\Library\TenantManagement\Model\TestRunnerThemeInterface;

class GetDeliveryExecutionConfigurationResponse
{
    /** @var Delivery */
    private $delivery;

    /** @var DeliveryExecution */
    private $deliveryExecution;

    /** @var TestRunnerThemeInterface */
    private $testRunnerTheme;

    /** @var array|null */
    private $testRunnerConfiguration;

    public function __construct(
        Delivery $delivery,
        DeliveryExecution $deliveryExecution,
        ?TestRunnerThemeInterface $testRunnerTheme = null,
        ?array $testRunnerConfiguration = null,
    ) {
        $this->delivery = $delivery;
        $this->deliveryExecution = $deliveryExecution;
        $this->testRunnerTheme = $testRunnerTheme ?? new EmptyTestRunnerTheme();
        $this->testRunnerConfiguration = $testRunnerConfiguration;
    }

    public function getDelivery(): Delivery
    {
        return $this->delivery;
    }

    public function getDeliveryExecution(): DeliveryExecution
    {
        return $this->deliveryExecution;
    }

    public function getTestRunnerTheme(): TestRunnerThemeInterface
    {
        return $this->testRunnerTheme;
    }

    public function getTestRunnerConfiguration(): ?array
    {
        return $this->testRunnerConfiguration;
    }

    public function shouldOverrideUiLocale(): bool
    {
        if (empty($this->testRunnerConfiguration['options']['localization']['locale'])) {
            return false;
        }

        return $this->testRunnerConfiguration['options']['localization']['isMultiLanguage'] ?? false;
    }

    public function getTranslatedTestLocale(): ?string
    {
        return $this->shouldOverrideUiLocale()
            ? $this->testRunnerConfiguration['options']['localization']['locale']
            : null;
    }
}
