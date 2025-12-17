<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Lti;

use App\Environment\FeatureFlagAdapterInterface;
use App\Generator\UrlGenerator;
use App\Lti\Exception\LtiCustomSettingsException;
use App\Lti\LtiCustomSettings;
use App\Lti\UserIdentity\AnonymousUserIdentity;
use App\Service\Locale\Contract\UserLocaleProviderInterface;
use App\Service\Locale\Dto\UserLocaleProviderContext;
use App\Service\Lti\Dto\StartProctoringRequestContext;
use OAT\Library\EnvironmentManagementClient\Repository\ConfigurationRepositoryInterface;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ContextClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\LaunchPresentationClaim;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Resource\LtiResourceLink\LtiResourceLink;
use OAT\Library\Lti1p3Proctoring\Message\Launch\Builder\StartProctoringLaunchRequestBuilder;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class LtiProctoringService
{
    public const FEATURE_FLAG = 'acs.service';
    private const PROCTORING_REGISTRATION_ID = 'proctoring.registration_id';

    public function __construct(
        private StartProctoringLaunchRequestBuilder $startProctoringLaunchRequestBuilder,
        private UrlGenerator $urlGenerator,
        private RegistrationRepositoryInterface $registrationRepository,
        private ConfigurationRepositoryInterface $configurationRepository,
        private LtiCustomSettings $ltiCustomSettings,
        private UserLocaleProviderInterface $userLocaleProvider,
        private FeatureFlagAdapterInterface $featureFlagAdapter,
        private int $acsUrlReferenceType = UrlGeneratorInterface::ABSOLUTE_URL,
    ) {
    }

    /**
     * @throws LtiCustomSettingsException
     * @throws LtiExceptionInterface
     */
    public function getStartProctoringRequestUrl(
        StartProctoringRequestContext $startProctoringRequestContext,
        bool $allowAuthorizationRequest = true,
    ): string {
        $tenantId = $startProctoringRequestContext->delivery->getTenantId();
        $ltiMessagePayload = $startProctoringRequestContext->ltiMessagePayload;
        $this->validatePayload($ltiMessagePayload, $startProctoringRequestContext->deliveryExecution->getId());

        $proctoringToolRegistrationId = $this->ltiCustomSettings->getProctoringRegistrationId(
            ['custom' => $ltiMessagePayload->getCustom() ?? []],
        ) ?: $this->configurationRepository->find($tenantId, self::PROCTORING_REGISTRATION_ID)->getStringValue();
        $proctoringContextId = $this->ltiCustomSettings->getProctoringContextId(
            ['custom' => $ltiMessagePayload->getCustom() ?? []],
        );

        $proctoringRegistration = $this->registrationRepository->find($proctoringToolRegistrationId);

        $startAssessmentLink = $this->urlGenerator->generate(
            'api_v1_proctoring_start_assessment',
            ['deliveryExecutionId' => $startProctoringRequestContext->deliveryExecution->getId()],
        );

        $assessmentControlLink = $this->urlGenerator->generate(
            'api_v1_proctoring_assessment_control',
            ['deliveryExecutionId' => $startProctoringRequestContext->deliveryExecution->getId()],
            $this->acsUrlReferenceType,
        );

        $userIdentity = $ltiMessagePayload->getUserIdentity()
            ?? new AnonymousUserIdentity($startProctoringRequestContext->deliveryExecution);
        $loginHint = array_merge(
            $userIdentity->normalize(),
            [
                'delivery_execution_id' => $startProctoringRequestContext->deliveryExecution->getId(),
            ],
        );

        $launchPresentation = $ltiMessagePayload->getLaunchPresentation();

        $isSessionAuthorizable = $allowAuthorizationRequest
            && $startProctoringRequestContext->deliveryExecution->isStateAvailableForAuthorisation();
        $proctoringCustomSettings = [
            'requireAuthorization' => $isSessionAuthorizable
                && $this->ltiCustomSettings->isProctorAuthorizationRequired(
                    ['custom' => $ltiMessagePayload->getCustom() ?? []],
                ),
            'forceAuthorization' => $this->ltiCustomSettings->isProctorAuthorizationForced(
                $isSessionAuthorizable,
                ['custom' => $ltiMessagePayload->getCustom() ?? []],
            ),
        ];

        $optionalClaims = [
            LtiMessagePayloadInterface::CLAIM_LTI_PROCTORING_SETTINGS => [
                'data' => json_encode($proctoringCustomSettings),
            ],
            LtiMessagePayloadInterface::CLAIM_LTI_CONTEXT => $proctoringContextId
                ? new ContextClaim($proctoringContextId)
                : $ltiMessagePayload->getContext(),
            LtiMessagePayloadInterface::CLAIM_LTI_LAUNCH_PRESENTATION => new LaunchPresentationClaim(
                $launchPresentation?->getDocumentTarget(),
                $launchPresentation?->getHeight(),
                $launchPresentation?->getWidth(),
                $launchPresentation?->getReturnUrl(),
                $this->userLocaleProvider->provide(
                    new UserLocaleProviderContext(
                        $startProctoringRequestContext->deliveryExecution,
                        $startProctoringRequestContext->delivery,
                    ),
                ),
            ),
        ];
        $proctoringCustomClaims = $this->ltiCustomSettings->getProctoringCustomClaims(
            ['custom' => $ltiMessagePayload->getCustom() ?? []],
        );
        if ($proctoringCustomClaims) {
            $optionalClaims[LtiMessagePayloadInterface::CLAIM_LTI_CUSTOM] = $proctoringCustomClaims;
        }
        if ($this->featureFlagAdapter->isEnabled($tenantId, self::FEATURE_FLAG, true)) {
            $optionalClaims[LtiMessagePayloadInterface::CLAIM_LTI_ACS] = [
                'actions' => AcsControlInterface::SUPPORTED_ACTIONS,
                'assessment_control_url' => $assessmentControlLink,
            ];
        }
        $proctoringRequest = $this->startProctoringLaunchRequestBuilder->buildStartProctoringLaunchRequest(
            new LtiResourceLink($ltiMessagePayload->getResourceLink()->getIdentifier()),
            $proctoringRegistration,
            $startAssessmentLink,
            json_encode($loginHint, JSON_THROW_ON_ERROR),
            1,
            $proctoringRegistration->getDefaultDeploymentId(),
            $ltiMessagePayload->getRoles(),
            $optionalClaims,
        );

        return $proctoringRequest->toUrl();
    }

    private function validatePayload(LtiMessagePayloadInterface $ltiMessagePayload, string $deliveryExecutionId): void
    {
        if (null === $ltiMessagePayload->getResourceLink()) {
            throw new LtiCustomSettingsException('ResourceLink is mandatory with proctoring authorisation control');
        }
    }
}
