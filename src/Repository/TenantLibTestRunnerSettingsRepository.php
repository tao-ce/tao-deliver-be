<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Delivery\Model\Delivery;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\Tenant\Model\TestRunnerSettings;
use App\Domain\Tenant\Model\TestRunnerSettingsRepositoryInterface;
use Carbon\Carbon;
use InvalidArgumentException;
use OAT\Library\EnvironmentManagementClient\Exception\EnvironmentManagementClientException;
use OAT\Library\EnvironmentManagementClient\Model\ConfigurationInterface;
use OAT\Library\EnvironmentManagementClient\Repository\ConfigurationRepositoryInterface;
use OAT\Library\TenantManagement\Model\TestRunnerTheme;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use TypeError;

class TenantLibTestRunnerSettingsRepository implements TestRunnerSettingsRepositoryInterface
{
    private const TEST_RUNNER_CONFIGURATION = 'testRunnerConfiguration';
    private const UI_ENGINE = 'ui-engine';
    private const DELIVERY_METADATA = 'delivery-metadata';

    /** @var array<string, TestRunnerSettings> */
    private array $testRunnerSettingsMap = [];

    public function __construct(
        private readonly DeliveryRepository $deliveryRepository,
        private readonly ConfigurationRepositoryInterface $configurationRepository,
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    public function findTestRunnerSettings(DeliveryExecution $deliveryExecution): TestRunnerSettings
    {
        $tenantId = $deliveryExecution->getTenantId();

        if (!isset($this->testRunnerSettingsMap[$tenantId])) {
            $delivery = $deliveryExecution->isReview()
                ? new Delivery(
                    $deliveryExecution->getDeliveryId(),
                    $deliveryExecution->getTenantId(),
                    Carbon::now(),
                    $deliveryExecution->getQtiCompactTestFilePath(),
                )
                : $this->deliveryRepository->find($deliveryExecution->getDeliveryId());

            $configuration = $this->findTestRunnerConfiguration($delivery);
            $themeConfiguration = $this->configurationRepository->find($tenantId, 'testRunnerTheme');
            $configArray = $configuration->getArrayValue();
            $configArray[self::DELIVERY_METADATA] = $delivery->getMetadata();
            $themeConfigArray = $themeConfiguration->getArrayValue();
            $theme = null;

            if (!empty($themeConfigArray)) {
                $theme = $this->normalizer->denormalize($themeConfigArray, TestRunnerTheme::class);
            }

            $this->testRunnerSettingsMap[$tenantId] = new TestRunnerSettings(
                $delivery,
                $configArray,
                $theme,
            );
        }

        return $this->testRunnerSettingsMap[$tenantId];
    }

    private function findTestRunnerConfiguration(Delivery $delivery): ConfigurationInterface
    {
        try {
            $uiEngine = $delivery->getMetadataPropertyValue(self::UI_ENGINE);

            if ($uiEngine === null) {
                throw new InvalidArgumentException();
            }

            return $this->configurationRepository->find(
                $delivery->getTenantId(),
                $uiEngine,
            );
        } catch (EnvironmentManagementClientException | TypeError | InvalidArgumentException) {
            return $this->configurationRepository->find($delivery->getTenantId(), self::TEST_RUNNER_CONFIGURATION);
        }
    }
}
