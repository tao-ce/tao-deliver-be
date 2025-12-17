<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Enrollment;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Repository\EnrollmentRepository;
use App\Domain\Tenant\Model\PortalSettingsRepositoryInterface;
use OAT\Library\EnvironmentManagementClient\Exception\EnvironmentManagementClientException;
use TypeError;

readonly class EnrollmentService
{
    public function __construct(
        private EnrollmentRepository $enrollmentRepository,
        private PortalSettingsRepositoryInterface $portalSettingsRepository,
    ) {
    }

    public function getSessionDataByDeliveryExecution(DeliveryExecution $deliveryExecution): ?array
    {
        $enrollment = $this->enrollmentRepository->findSession($deliveryExecution);
        if (empty($enrollment)) {
            return null;
        }

        $presentTestCategories = [];
        try {
            $testCategories = $this->portalSettingsRepository->findTestCategories($deliveryExecution);
            foreach ($enrollment->getTestCategory() as $categoryId) {
                if (isset($testCategories[$categoryId])) {
                    $presentTestCategories[] = $testCategories[$categoryId];
                }
            }
        } catch (EnvironmentManagementClientException | TypeError) {
            // if test categories are not found, we can still return the enrollment data without them
            // because session data is still valid and not certification-specific
        }

        return [
            'campaignId' => $enrollment->getCampaignId(),
            'campaignName' => $enrollment->getCampaignName(),
            'sessionId' => $enrollment->getSessionId(),
            'sessionName' => $enrollment->getSessionName(),
            'sessionTemplateId' => $enrollment->getSessionTemplateId(),
            'sessionTemplateName' => $enrollment->getSessionTemplateName(),
            'enrollmentTestCategories' => $presentTestCategories,
        ];
    }
}
