<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Lti\DeepLinking;

use App\Action\Lti\LtiActionTrait;
use App\Action\Security\Lti\LaunchLti1p3Action;
use App\Action\Security\Lti\LaunchLti1p3BatteryAction;
use App\Generator\UuidGenerator;
use App\Service\ApplicationInfoService;
use Exception;
use OAT\Bundle\EnvironmentManagementClientBundle\Http\ResponseHelper;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\Launch\Validator\Tool\ToolLaunchValidatorInterface;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use OAT\Library\Lti1p3Core\Message\Payload\MessagePayloadInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3DeepLinking\Message\Launch\Builder\DeepLinkingLaunchResponseBuilder;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class GetDeepLinksAction
{
    use LtiActionTrait;
    use LtiDeepLinkingActionTrait;
    use LtiDeepLinkingLaunchTrait;

    public function __construct(
        private readonly PsrHttpFactory $psrHttpFactory,
        private readonly UuidGenerator $uuidGenerator,
        private readonly ApplicationInfoService $applicationInfoService,
        private readonly ResponseHelper $responseHelper,
        private readonly DeepLinkingLaunchResponseBuilder $deepLinkingLaunchResponseBuilder,
        private readonly RegistrationRepositoryInterface $registrationRepository,
        private readonly ToolLaunchValidatorInterface $toolLaunchValidator,
        private readonly LaunchLti1p3Action $launchLti1p3Action,
        private readonly LaunchLti1p3BatteryAction $launchLti1p3BatteryAction,
        private readonly string $ltiDeepLinkingFrontendUrl,
    ) {
    }

    /**
     * @throws LtiExceptionInterface
     */
    public function __invoke(
        Request $request,
        LtiMessagePayloadInterface $ltiMessagePayload,
        string $tenantId,
    ): Response {
        $psrRequest = $this->psrHttpFactory->createRequest($request);
        $result = $this->toolLaunchValidator->validatePlatformOriginatingLaunch($psrRequest);

        if ($result->hasError()) {
            throw new BadRequestHttpException($result->getError());
        }

        if ($this->isLtiResourceLinkRequest($ltiMessagePayload)) {
            return $this->redirectToTargetLinkUri($request, $ltiMessagePayload);
        }

        $registration = $this->getLtiRegistrationFromLtiMessagePayload($ltiMessagePayload);
        $settings = $ltiMessagePayload->getDeepLinkingSettings();

        if (!$settings) {
            throw new BadRequestHttpException(sprintf(
                '%s claim is required',
                LtiMessagePayloadInterface::CLAIM_LTI_DEEP_LINKING_SETTINGS,
            ));
        }

        try {
            $sessionId = $this->uuidGenerator->generate();

            $redirectQueryParams = [
                'sessionId' => $sessionId,
            ];

            if ($request->query->has('hideBatteries')) {
                $redirectQueryParams['hideBatteries'] = $request->query->get('hideBatteries');
            }

            if ($request->query->has('hideDeliveries')) {
                $redirectQueryParams['hideDeliveries'] = $request->query->get('hideDeliveries');
            }

            return $this->responseHelper->withAuthorizationDetailsMarker(
                new RedirectResponse(sprintf('%s?%s', $this->ltiDeepLinkingFrontendUrl, http_build_query($redirectQueryParams))),
                current($ltiMessagePayload->getMandatoryClaim(MessagePayloadInterface::CLAIM_AUD)),
                $sessionId,
                cookieDomain: parse_url($this->applicationInfoService->getBackendUrl(), PHP_URL_HOST),
                ltiToken: $this->getLtiToken($request),
            );
        } catch (Exception $exception) {
            return $this->getDeepLinkingErrorResponse(
                $registration,
                $settings,
                $exception,
                redirect: true,
            );
        }
    }

    private function getLtiToken(Request $request): ?string
    {
        switch ($request->getMethod()) {
            case Request::METHOD_GET:
                if (!$request->query->has('id_token')) {
                    throw new BadRequestHttpException('Mandatory "id_token" query parameter is missing');
                }
                return $request->query->get('id_token');

            case Request::METHOD_POST:
                if (!$request->request->has('id_token')) {
                    throw new BadRequestHttpException('Mandatory "id_token" request parameter is missing');
                }
                return $request->request->get('id_token');

            default:
                throw new MethodNotAllowedHttpException([Request::METHOD_GET, Request::METHOD_POST]);
        }
    }
}
