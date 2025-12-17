<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Lti\DeepLinking;

use App\Action\Security\Lti\LaunchLti1p3Action;
use App\Action\Security\Lti\LaunchLti1p3BatteryAction;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait LtiDeepLinkingLaunchTrait
{
    public function __construct(
        private readonly LaunchLti1p3Action $launchLti1p3Action,
        private readonly LaunchLti1p3BatteryAction $launchLti1p3BatteryAction,
    ) {
    }

    /**
     * @throws LtiExceptionInterface
     */
    private function redirectToTargetLinkUri(Request $request, LtiMessagePayloadInterface $ltiMessagePayload): Response
    {
        $targetLinkUri = $ltiMessagePayload->getTargetLinkUri();
        if ($this->isDeliveryLaunchUri($targetLinkUri)) {
            return ($this->launchLti1p3Action)($request, $ltiMessagePayload);
        } elseif ($this->isBatteryLaunchUri($targetLinkUri)) {
            return ($this->launchLti1p3BatteryAction)($request, $ltiMessagePayload);
        } else {
            throw new BadRequestHttpException(sprintf('Bad claim target link uri: %s', $targetLinkUri));
        }
    }

    private function isLtiResourceLinkRequest(LtiMessagePayloadInterface $ltiMessagePayload): bool
    {
        return $ltiMessagePayload->getMessageType() === LtiMessageInterface::LTI_MESSAGE_TYPE_RESOURCE_LINK_REQUEST;
    }

    private function isDeliveryLaunchUri(string $targetLinkUri): bool
    {
        return in_array('launch-lti-1p3', explode('/', $targetLinkUri));
    }

    private function isBatteryLaunchUri(string $targetLinkUri): bool
    {
        return in_array('launch-lti-1p3-battery', explode('/', $targetLinkUri));
    }
}
