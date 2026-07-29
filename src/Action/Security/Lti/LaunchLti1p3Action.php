<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Security\Lti;

use App\Lti\Exception\LtiLaunchException;
use App\Lti\LtiCustomSettings;
use App\Service\Lti\LtiLaunchService;
use Exception;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LaunchLti1p3Action
{
    use LtiLaunchActionCommonTrait;

    public function __construct(
        private LtiLaunchService $ltiLaunchService,
        private LtiCustomSettings $ltiCustomSettings,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request, LtiMessagePayloadInterface $ltiMessagePayload): Response
    {
        $parameters = $this->getParameters($request, $ltiMessagePayload);

        $this->validateRoles($ltiMessagePayload);

        $this->logger->info('validateClaims.');

        $this->ltiCustomSettings->validateClaims($parameters);

        $targetLinkUriParts = explode('/', $ltiMessagePayload->getTargetLinkUri());
        try {
            return $this->ltiLaunchService->launch(
                end($targetLinkUriParts) ?: '',
                $parameters,
                $ltiMessagePayload,
            );
        } catch (Exception $e) {
            throw (new LtiLaunchException(message: $e->getMessage(), previous: $e))->setLtiMessage($ltiMessagePayload);
        }
    }
}
