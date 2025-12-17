<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Repository;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\Tenant\Model\PortalSettingsRepositoryInterface;
use OAT\Library\EnvironmentManagementClient\Repository\ConfigurationRepositoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TenantLibPortalSettingsRepository implements PortalSettingsRepositoryInterface
{
    private const TEST_CATEGORIES = 'portal.test_categories';

    /**
     * @var array<string, array>
     */
    public function __construct(
        private readonly ConfigurationRepositoryInterface $configurationRepository,
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    public function findTestCategories(DeliveryExecution $deliveryExecution): array
    {
        $configuration = $this->configurationRepository->find($deliveryExecution->getTenantId(), self::TEST_CATEGORIES);

        $result = [];
        foreach ($configuration->getArrayValue() as $item) {
            $result[$item['id']] = $item;
        }
        return $result;
    }
}
