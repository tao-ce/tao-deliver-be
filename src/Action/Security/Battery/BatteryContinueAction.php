<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Security\Battery;

use App\Lti\LtiCustomSettings;
use App\Repository\DeliveryRepository;
use App\Security\Contract\DeliveryExecutionSessionController;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\Lti\Dto\StartProctoringRequestContext;
use App\Service\Lti\LtiLaunchService;
use App\TestRunner\Service\BatteryNavigationService;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayload;
use OAT\Library\Lti1p3Core\Security\Jwt\Token;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

readonly class BatteryContinueAction implements DeliveryExecutionSessionController
{
    public function __construct(
        private BatteryNavigationService $batteryNavigationService,
        private LtiLaunchService $ltiLaunchService,
        private DeliveryExecutionServiceInterface $deliveryExecutionService,
        private LtiCustomSettings $ltiCustomSettings,
        private DeliveryRepository $deliveryRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        string $id,
    ): Response {
        $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecutionOrFail($id);
        $nextDeliveryExecution = $this->batteryNavigationService->getNextDeliveryExecution(
            $deliveryExecution,
            $this->batteryNavigationService->getBatteryDistribution($deliveryExecution),
        );
        $delivery = $this->deliveryRepository->find($nextDeliveryExecution->getDeliveryId());
        $params = $nextDeliveryExecution->getLtiLaunchParameters();

        $this->logger->info(
            sprintf(
                'Continuing battery execution [battery=%s,delivery=%s,deliveryExecution=%s]',
                $params['battery_id'] ?? null,
                $nextDeliveryExecution->getDeliveryId(),
                $nextDeliveryExecution->getId(),
            ),
        );

        if ($this->ltiCustomSettings->isMonitoringEnabled($nextDeliveryExecution->getLtiLaunchParameters())) {
            return $this->ltiLaunchService->requireAuthorization(
                new StartProctoringRequestContext(
                    new LtiMessagePayload(
                        new Token(
                            Configuration::forSymmetricSigner(
                                new Sha256(),
                                InMemory::empty(),
                            )->parser()->parse($nextDeliveryExecution->getLtiToken()),
                        ),
                    ),
                    $nextDeliveryExecution,
                    $delivery,
                ),
                false,
            );
        }

        return $this->ltiLaunchService->launchTest($nextDeliveryExecution, $params);
    }
}
