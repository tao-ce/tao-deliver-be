<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Battery;

use App\Lti\LtiCustomSettings;
use App\Repository\BatteryDistributionRepository;
use App\Service\Battery\Dto\BatteryPasswordValidationCommand;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use InvalidArgumentException;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class BatteryPasswordValidationService
{
    public function __construct(
        private readonly DeliveryExecutionService $deliveryExecutionService,
        private readonly LoggerInterface $logger,
        private readonly BatteryDistributionRepository $batteryDistributionRepository,
    ) {
    }

    /**
     * @throws DocumentNotFoundException
     */
    public function validate(BatteryPasswordValidationCommand $command): void
    {
        $deliveryExecution = $this->deliveryExecutionService
            ->findDeliveryExecutionOrFail($command->deliveryExecutionId);

        $launchParams = $deliveryExecution->getLtiLaunchParameters();
        $batteryId = $launchParams['battery_id'] ?? null;
        $userId = $launchParams['user_id'] ?? null;
        $attemptId = $deliveryExecution->getAttemptId();
        $logPrefix = sprintf(
            'Error validating password [user:%s,battery:%s,delivery:%s,delivery-execution:%s]',
            $userId,
            $batteryId,
            $command->deliveryId,
            $deliveryExecution->getId(),
        );

        if ($batteryId === null) {
            $error = sprintf('%s: There is not battery associated to the delivery execution', $logPrefix);

            $this->logger->error($error);

            throw new InvalidArgumentException($error);
        }

        $batteryDistribution = $this->batteryDistributionRepository->findByBatteryAndUserId($batteryId, $userId, $attemptId);
        $batteryDelivery = $batteryDistribution->battery->getDelivery($command->deliveryId);

        if ($batteryDelivery === null) {
            $error = sprintf('%s: The delivery does not exist in the battery', $logPrefix);

            $this->logger->error(sprintf('Trying to validate password for a non-battery %s', $logPrefix));

            throw new InvalidArgumentException($error);
        }

        if ($batteryDelivery->isPasswordProtected() && !$batteryDelivery->matchPassword($command->password)) {
            $error = sprintf('%s: Unauthorized access', $logPrefix);

            $this->logger->warning($error);

            throw new UnauthorizedHttpException('', $error);
        }
    }
}
