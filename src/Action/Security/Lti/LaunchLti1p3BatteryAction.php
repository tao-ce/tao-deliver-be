<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Security\Lti;

use App\Domain\Battery\Model\Battery;
use App\Generator\UuidGenerator;
use App\Lti\Exception\LtiCustomSettingsException;
use App\Lti\Exception\LtiLaunchAuthException;
use App\Lti\Exception\LtiLaunchException;
use App\Lti\LtiCustomSettings;
use App\Service\Battery\BatteryService;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use App\Service\Lti\LtiLaunchService;
use Exception;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class LaunchLti1p3BatteryAction
{
    use LtiLaunchActionCommonTrait;

    public function __construct(
        private readonly LtiLaunchService $ltiLaunchService,
        private readonly LtiCustomSettings $ltiCustomSettings,
        private readonly LoggerInterface $logger,
        private readonly BatteryService $batteryService,
        private readonly UuidGenerator $uuidGenerator,
    ) {
    }

    /**
     * @throws LtiLaunchException
     * @throws LtiLaunchAuthException
     * @throws LtiCustomSettingsException
     */
    public function __invoke(Request $request, LtiMessagePayloadInterface $ltiMessagePayload): RedirectResponse
    {
        $this->validateRoles($ltiMessagePayload);

        $parameters = $this->getParameters($request, $ltiMessagePayload);

        $this->logger->info('validateClaims.');
        $this->ltiCustomSettings->validateClaims($parameters);

        if (empty($parameters['user_id'])) {
            $parameters[DeliveryExecutionService::PARAM_ANONYMOUS_USER_ID] = 'anonymous-'
                . $this->uuidGenerator->generateMedium();
        }

        $targetLinkUriParts = explode('/', $ltiMessagePayload->getTargetLinkUri());

        try {
            $battery = $this->batteryService->findBatteryOrFail(end($targetLinkUriParts) ?: '');
            $parameters['user_id'] = $parameters['user_id'] ?? $parameters[DeliveryExecutionService::PARAM_ANONYMOUS_USER_ID];
            $delivery = $this->batteryService->getAssignedDelivery(
                $battery,
                $parameters,
            );
            $this->addBatteryParameters($parameters, $battery);

            return $this->ltiLaunchService->launch(
                $delivery->id,
                $parameters,
                $ltiMessagePayload,
            );
        } catch (Exception $e) {
            throw (new LtiLaunchException(message: $e->getMessage(), previous: $e))->setLtiMessage($ltiMessagePayload);
        }
    }

    private function addBatteryParameters(array &$parameters, Battery $battery): void
    {
        $parameters['battery_id'] = $battery->getId();
        $parameters['battery_name'] = $battery->name;
        $parameters['battery_description'] = $battery->description;
    }
}
