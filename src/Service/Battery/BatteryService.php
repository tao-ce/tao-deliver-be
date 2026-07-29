<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Battery;

use App\Domain\Battery\Exception\EmptyBatteryException;
use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDelivery;
use App\Domain\DeliveryExecution\Helper\DeliveryExecutionKeyHelper;
use App\Lti\LtiCustomSettings;
use App\Repository\BatteryDistributionRepository;
use App\Repository\BatteryRepository;
use App\Repository\DeliveryRepository;
use App\Service\BatteryDistribution\BatteryDeliveryToExecuteRetriever;
use App\Service\Infrastructure\Contract\MemoizedService;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WeakMap;

class BatteryService implements MemoizedService
{
    private WeakMap $deliveries;

    public function __construct(
        private readonly BatteryRepository $batteryRepository,
        private readonly BatteryDistributionRepository $batteryDistributionRepository,
        private readonly BatteryDeliveryToExecuteRetriever $batteryDeliveryToExecuteRetriever,
        private readonly DeliveryRepository $deliveryRepository,
        private readonly LtiCustomSettings $ltiCustomSettings,
    ) {
        $this->flush();
    }

    public function flush(): void
    {
        $this->deliveries = new WeakMap();
    }

    public function findBatteryOrFail(string $batteryId): Battery
    {
        try {
            return $this->batteryRepository->find($batteryId);
        } catch (DocumentNotFoundException $exception) {
            throw new NotFoundHttpException(
                sprintf('[IRRECOVERABLE] Battery with id %s not found', $batteryId),
                $exception,
            );
        }
    }

    /**
     * @throws EmptyBatteryException
     */
    public function getAssignedDelivery(Battery $battery, array $ltiLaunchParameters): BatteryDelivery
    {
        $reviewableDelivery = $this->getReviewableDelivery($battery, $ltiLaunchParameters);
        if (null !== $reviewableDelivery) {
            return $reviewableDelivery;
        }
        $userId = $ltiLaunchParameters['user_id'];
        $battery = $this->batteryDeliveryToExecuteRetriever->filter(
            $battery,
            $ltiLaunchParameters,
        );
        $attemptId = $ltiLaunchParameters['custom'][LtiCustomSettings::PARAM_ATTEMPT_ID] ?? null;
        if ($this->ltiCustomSettings->isResetEnabled($ltiLaunchParameters)) {
            return $this->batteryDistributionRepository->createByBatteryAndUserId(
                $battery,
                $userId,
                $attemptId,
            )->battery->getFirstDelivery();
        }

        $batteryDistribution = $this->batteryDistributionRepository->findOrCreate($battery, $userId, $attemptId);
        return $this->batteryDeliveryToExecuteRetriever->retrieve(
            $batteryDistribution,
            $ltiLaunchParameters,
        );
    }

    public function getCommonLocales(Battery $battery): array
    {
        $localesList = [];

        foreach ($this->getDeliveries($battery) as $delivery) {
            $localesList[] = $delivery->getSupportedLocales();
        }

        return array_values(array_intersect(...$localesList));
    }

    public function isMultiLanguageBattery(Battery $battery): bool
    {
        foreach ($this->getDeliveries($battery) as $delivery) {
            if (!$delivery->isMultiLanguage()) {
                return false;
            }
        }

        return true;
    }

    private function getReviewableDelivery(Battery $battery, array $ltiLaunchParameters): ?BatteryDelivery
    {
        $userId = $ltiLaunchParameters['user_id'];
        $attemptId = $ltiLaunchParameters['custom'][LtiCustomSettings::PARAM_ATTEMPT_ID] ?? null;

        if (!$this->ltiCustomSettings->isReviewModeEnabled($ltiLaunchParameters)) {
            return null;
        }

        $batteryDistribution = $this->batteryDistributionRepository->findByBatteryAndUserId($battery->getId(), $userId, $attemptId);
        $deliveryExecutionId = $this->ltiCustomSettings->getReviewDeliveryExecutionId($ltiLaunchParameters);
        if (null === $deliveryExecutionId) {
            return $batteryDistribution->battery->getFirstDelivery();
        }

        $deliveryExecutionKey = DeliveryExecutionKeyHelper::createDeliveryExecutionKeyInfo($deliveryExecutionId);
        if (null === $deliveryExecutionKey) {
            return $batteryDistribution->battery->getFirstDelivery();
        }

        return $batteryDistribution->battery->getDelivery($deliveryExecutionKey->getDeliveryId());
    }

    private function getDeliveries(Battery $battery): iterable
    {
        foreach ($battery->deliveries as $batteryDelivery) {
            if (!isset($this->deliveries[$battery][$batteryDelivery->id])) {
                $this->deliveries[$battery] ??= [];
                $this->deliveries[$battery][$batteryDelivery->id] = $this->deliveryRepository->find(
                    $batteryDelivery->id,
                );
            }
        }

        return $this->deliveries[$battery];
    }
}
