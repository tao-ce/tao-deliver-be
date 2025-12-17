<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\TestRunner\Service;

use App\Domain\Battery\Model\BatteryDistribution;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Generator\UrlGenerator;
use App\Lti\LtiCustomSettings;
use App\Repository\BatteryDistributionRepository;
use App\Repository\DeliveryRepository;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class BatteryNavigationService
{
    public function __construct(
        private LtiCustomSettings $ltiCustomSettings,
        private DeliveryExecutionServiceInterface $deliveryExecutionService,
        private DeliveryRepository $deliveryRepository,
        private BatteryDistributionRepository $batteryDistributionRepository,
        private UrlGenerator $urlGenerator,
    ) {
    }

    public function getBatteryDistribution(DeliveryExecution $deliveryExecution): ?BatteryDistribution
    {
        $launchParameters = $deliveryExecution->getOriginalLtiLaunchParameters();
        if (empty($launchParameters['battery_id'])) {
            return null;
        }

        try {
            return $this->batteryDistributionRepository->findByBatteryAndUserId(
                $launchParameters['battery_id'],
                $launchParameters['user_id'] ?? $deliveryExecution->getUserId(),
                $deliveryExecution->getAttemptId(),
            );
        } catch (DocumentNotFoundException) {
        }
        return null;
    }

    public function getNextDeliveryExecution(
        DeliveryExecution $deliveryExecution,
        ?BatteryDistribution $batteryDistribution,
        array $actionParameters = [],
    ): ?DeliveryExecution {
        if ($batteryDistribution === null) {
            return null;
        }

        $actionParameters = array_merge($deliveryExecution->getOriginalLtiLaunchParameters(), $actionParameters);
        unset($actionParameters['result_id']);

        $currentBatteryDelivery = $batteryDistribution->battery->getDelivery($deliveryExecution->getDeliveryId());
        $nextBatteryDelivery = $batteryDistribution->battery->getNextDelivery($deliveryExecution->getDeliveryId());

        if ($currentBatteryDelivery === null || $nextBatteryDelivery === null) {
            return null;
        }

        try {
            return $this->deliveryExecutionService->getDeliveryExecution(
                $this->deliveryRepository->find($nextBatteryDelivery->id),
                $actionParameters,
                $deliveryExecution->getLocale(),
            );
        } catch (NotFoundHttpException) {
        }

        return null;
    }

    public function getBatteryContext(
        DeliveryExecution $deliveryExecution,
        array $actionParameters = [],
    ): ?array {
        $launchParameters = $deliveryExecution->getOriginalLtiLaunchParameters();
        $batteryDistribution = $this->getBatteryDistribution($deliveryExecution);

        if (
            !$deliveryExecution->isReview()
            && $this->ltiCustomSettings->isAutoReviewModeEnabled($launchParameters)
        ) {
            return [
                'batteryDistribution' => $this->createBatteryDistributionData($batteryDistribution),
                'nextDeliveryExecutionUrl' => $this->urlGenerator->generate(
                    'api_v1_auto_review',
                    ['id' => $deliveryExecution->getId()],
                ),
            ];
        }

        $nextDeliveryExecution = $this->getNextDeliveryExecution(
            $deliveryExecution,
            $batteryDistribution,
            $actionParameters,
        );
        if ($nextDeliveryExecution === null) {
            return null;
        }

        if (
            $deliveryExecution->isReview()
            && !$this->ltiCustomSettings->isAutoReviewModeEnabled($launchParameters)
        ) {
            return [
                'batteryDistribution' => $this->createBatteryDistributionData($batteryDistribution),
                'nextDeliveryExecutionUrl' => $this->urlGenerator->generate(
                    'api_v1_battery_review',
                    [
                        'id' => $nextDeliveryExecution->getId(),
                        'batteryId' => $batteryDistribution->battery->getId(),
                    ],
                ),
            ];
        }

        $currentBatteryDelivery = $batteryDistribution->battery->getDelivery($deliveryExecution->getDeliveryId());
        $nextBatteryDelivery = $batteryDistribution->battery->getNextDelivery($deliveryExecution->getDeliveryId());

        return [
            'batteryDistribution' => $this->createBatteryDistributionData($batteryDistribution),
            'currentDelivery' => [
                'id' => $currentBatteryDelivery->id,
                'order' => $currentBatteryDelivery->order,
                'isPasswordProtected' => $currentBatteryDelivery->isPasswordProtected(),
            ],
            'nextDelivery' => [
                'id' => $nextBatteryDelivery->id,
                'order' => $nextBatteryDelivery->order,
                'isPasswordProtected' => $nextBatteryDelivery->isPasswordProtected(),
            ],
            'nextDeliveryExecutionUrl' => $this->urlGenerator->generate(
                'api_v1_battery_continue',
                ['id' => $deliveryExecution->getId()],
            ),
        ];
    }

    private function createBatteryDistributionData(?BatteryDistribution $batteryDistribution): array
    {
        return [
            'id' => $batteryDistribution?->getId(),
            'locale' => $batteryDistribution?->getLocale(),
        ];
    }
}
