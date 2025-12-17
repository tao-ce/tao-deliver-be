<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Security\Lti\Proctoring;

use App\Lti\UserIdentity\AnonymousUserIdentity;
use App\Repository\DeliveryRepository;
use App\Security\Contract\DeliveryExecutionSessionController;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use App\Service\Locale\Contract\UserLocaleProviderInterface;
use App\Service\Locale\Dto\UserLocaleProviderContext;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Library\EnvironmentManagementClient\Exception\LtiRegistrationNotFoundException;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ContextClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\LaunchPresentationClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ResourceLinkClaim;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\User\UserIdentity;
use OAT\Library\Lti1p3Proctoring\Message\Launch\Builder\EndAssessmentLaunchRequestBuilder;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class EndAssessmentReturnAction implements DeliveryExecutionSessionController
{
    public function __construct(
        private string $deliverFrontendEndAssessmentUrl,
        private LoggerInterface $auditDeliveryExecutionLogger,
        private RepositoryAwareDeliveryExecutionServiceInterface $deliveryExecutionService,
        private DeliveryRepository $deliveryRepository,
        private RegistrationRepositoryInterface $registrationRepository,
        private EndAssessmentLaunchRequestBuilder $endAssessmentLaunchRequestBuilder,
        private UserLocaleProviderInterface $userLocaleProvider,
    ) {
    }

    public function __invoke(Request $request, string $deliveryExecutionId): Response
    {
        $this->auditDeliveryExecutionLogger->info(
            sprintf('[%s] - end assessment', $deliveryExecutionId),
        );
        try {
            $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecutionOrFail($deliveryExecutionId);
            $delivery = $this->deliveryRepository->find($deliveryExecution->getDeliveryId());
        } catch (DocumentNotFoundException $exception) {
            $this->auditDeliveryExecutionLogger->notice(
                sprintf('[%s] – %s, return to %s', $deliveryExecutionId, $exception->getMessage(), $this->deliverFrontendEndAssessmentUrl),
                compact('exception'),
            );
            return new RedirectResponse($this->deliverFrontendEndAssessmentUrl);
        }
        $ltiLaunchParameters = $deliveryExecution->getLtiLaunchParameters();
        $redirectUrl = $request->query->get('redirectUrl', '');
        if (
            !$redirectUrl
            || parse_url($redirectUrl, PHP_URL_HOST)
            !== parse_url($ltiLaunchParameters['launch_presentation_return_url'] ?? '', PHP_URL_HOST)) {
            $redirectUrl = $this->deliverFrontendEndAssessmentUrl;
        }
        if (
            empty($ltiLaunchParameters['assessment_platform_issuer'])
            || empty($ltiLaunchParameters['assessment_platform_client_id'])
        ) {
            $this->auditDeliveryExecutionLogger->notice(
                sprintf(
                    '[%s] - proctoring tool registration details not found, returning to %s',
                    $deliveryExecutionId,
                    $redirectUrl,
                ),
            );
            return new RedirectResponse($redirectUrl);
        }
        try {
            $registration = $this->registrationRepository->findByPlatformIssuer(
                $ltiLaunchParameters['assessment_platform_issuer'],
                $ltiLaunchParameters['assessment_platform_client_id'],
            );
        } catch (LtiRegistrationNotFoundException $exception) {
            $this->auditDeliveryExecutionLogger->notice(
                sprintf(
                    '[%s] - proctoring tool registration not found, return to %s',
                    $deliveryExecutionId,
                    $redirectUrl,
                ),
                compact('exception'),
            );
            return new RedirectResponse($redirectUrl);
        }

        $userIdentity = empty($ltiLaunchParameters['user_id'])
            ? new AnonymousUserIdentity($deliveryExecution)
            : new UserIdentity(
                $ltiLaunchParameters['user_id'],
                $ltiLaunchParameters['user_name'] ?? null,
                null,
                $ltiLaunchParameters['given_name'] ?? null,
                $ltiLaunchParameters['family_name'] ?? null,
                null,
                $ltiLaunchParameters['user_locale'] ?? null,
            );
        $loginHint = array_merge(
            $userIdentity->normalize(),
            [
                'delivery_execution_id' => $deliveryExecutionId,
            ],
        );
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY) ?: '', $redirectParameters);
        $claims = [
            LtiMessagePayloadInterface::CLAIM_LTI_CONTEXT => empty($ltiLaunchParameters['context_id'])
                ? null
                : new ContextClaim($ltiLaunchParameters['context_id']),
            LtiMessagePayloadInterface::CLAIM_LTI_RESOURCE_LINK => new ResourceLinkClaim(
                $ltiLaunchParameters['resource_link_id'],
            ),
            LtiMessagePayloadInterface::CLAIM_LTI_LAUNCH_PRESENTATION => new LaunchPresentationClaim(
                returnUrl: $redirectUrl,
                locale: $this->userLocaleProvider->provide(
                    new UserLocaleProviderContext(
                        $deliveryExecution,
                        $delivery,
                    ),
                ),
            ),
        ];
        if (!empty($redirectParameters['lti_errormsg'])) {
            $claims['https://purl.imsglobal.org/spec/lti-ap/claim/errormsg'] = $redirectParameters['lti_errormsg'];
            $claims['https://purl.imsglobal.org/spec/lti-ap/claim/errorlog'] = $redirectParameters['lti_errorlog'] ?? $redirectParameters['lti_errormsg'];
        }
        $endAssessmentUrl = $this->endAssessmentLaunchRequestBuilder->buildEndAssessmentLaunchRequest(
            $registration,
            json_encode($loginHint),
            roles: $ltiLaunchParameters['roles'],
            optionalClaims: $claims,
        )->toUrl();
        $this->auditDeliveryExecutionLogger->info(
            sprintf(
                '[%s] - end assessment with original return_url %s, return to proctoring %s',
                $deliveryExecutionId,
                $redirectUrl,
                $endAssessmentUrl,
            ),
        );

        return new RedirectResponse($endAssessmentUrl);
    }
}
