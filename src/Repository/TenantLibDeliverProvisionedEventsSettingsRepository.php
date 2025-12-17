<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Tenant\Model\DeliverProvisionedEventsSettingsRepositoryInterface;
use App\Domain\Tenant\Model\TenantAwareInterface;
use OAT\Library\EnvironmentManagementClient\Exception\ConfigurationNotFoundException;
use OAT\Library\EnvironmentManagementClient\Exception\EnvironmentManagementClientException;
use OAT\Library\EnvironmentManagementClient\Repository\ConfigurationRepositoryInterface;

class TenantLibDeliverProvisionedEventsSettingsRepository implements DeliverProvisionedEventsSettingsRepositoryInterface
{
    private const DELIVER_PROVISIONED_EVENTS = 'deliver.provisioned_events';
    private array $dataCache = [];

    /**
     * @var array<string, array>
     */
    public function __construct(
        private readonly ConfigurationRepositoryInterface $configurationRepository,
    ) {
    }

    public function findAssessmentLogSettings(TenantAwareInterface $deliveryExecution): array
    {
        $cacheKey = $deliveryExecution->getTenantId() . '_' . self::DELIVER_PROVISIONED_EVENTS;
        if (isset($this->dataCache[$cacheKey])) {
            return $this->dataCache[$cacheKey];
        }

        try {
            $this->dataCache = []; // reset cache for other tenants keep only one tenant data in cache
            return $this->dataCache[$cacheKey] = $this->configurationRepository->find(
                $deliveryExecution->getTenantId(),
                self::DELIVER_PROVISIONED_EVENTS,
            )?->getArrayValue()['assessmentLog'] ?? [];
        } catch (EnvironmentManagementClientException | ConfigurationNotFoundException) {
            return $this->dataCache[$cacheKey] = [
                'proctorActions' => [
                    '*',
                ],
                'systemActions' => [
                    '*',
                ],
                'testTakerActions' => [
                    'flag',
                    'pause',
                ],
            ];
        }
    }
}
