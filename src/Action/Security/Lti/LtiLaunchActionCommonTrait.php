<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Security\Lti;

use App\Lti\Exception\LtiLaunchAuthException;
use Exception;
use OAT\Library\Lti1p3Core\Exception\LtiException;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use OAT\Library\Lti1p3Core\Message\Payload\MessagePayloadInterface;
use OAT\Library\Lti1p3Core\Role\RoleInterface;
use Symfony\Component\HttpFoundation\Request;

trait LtiLaunchActionCommonTrait
{
    public function getParameters(Request $request, LtiMessagePayloadInterface $ltiMessage): array
    {
        $ltiParameters = [
            'lti_version' => $ltiMessage->getVersion(),
            'roles' => $ltiMessage->getRoles(),
            'resource_link_id' => $ltiMessage->getResourceLink()?->getIdentifier(),
            'lti_message_type' => $ltiMessage->getMessageType(),
            'platform_issuer' => $ltiMessage->getClaim(MessagePayloadInterface::CLAIM_ISS),
            'client_id' => current($ltiMessage->getClaim(MessagePayloadInterface::CLAIM_AUD)),
            'id_token' => $request->get('id_token') ?? $ltiMessage->getToken()->toString(),
        ];

        if ($ltiMessage->getAgs() !== null) {
            $ltiParameters['ags_claim'] = $ltiMessage->getAgs()->normalize();
        }

        if ($ltiMessage->getContext() !== null) {
            $ltiParameters['context_id'] = $ltiMessage->getContext()->getIdentifier();
        }

        if ($ltiMessage->getCustom() !== null) {
            $ltiParameters['custom'] = $ltiMessage->getCustom();
        }

        if ($ltiMessage->getUserIdentity() !== null) {
            $ltiParameters['user_id'] = $ltiMessage->getUserIdentity()->getIdentifier();
            $ltiParameters['user_name'] = $ltiMessage->getUserIdentity()->getName();
            $ltiParameters['given_name'] = $ltiMessage->getUserIdentity()->getGivenName();
            $ltiParameters['family_name'] = $ltiMessage->getUserIdentity()->getFamilyName();

            if ($ltiMessage->getUserIdentity()->getLocale() !== null) {
                $ltiParameters['user_locale'] = $ltiMessage->getUserIdentity()->getLocale();
            }
        } else {
            $ltiParameters['user_id'] = null;
            $ltiParameters['user_name'] = null;
        }

        if ($ltiMessage->getLaunchPresentation() !== null) {
            if ($ltiMessage->getLaunchPresentation()->getReturnUrl() !== null) {
                $ltiParameters['launch_presentation_return_url'] = $ltiMessage->getLaunchPresentation()->getReturnUrl();
            }

            if ($ltiMessage->getLaunchPresentation()->getLocale() !== null) {
                $ltiParameters['launch_presentation_locale'] = $ltiMessage->getLaunchPresentation()->getLocale();
            }
        }

        if ($ltiMessage->getBasicOutcome() !== null) {
            $ltiParameters['lis_outcome_service_url'] = $ltiMessage->getBasicOutcome()->getLisOutcomeServiceUrl();
            $ltiParameters['result_id'] = $ltiMessage->getBasicOutcome()->getLisResultSourcedId();
        }

        return $ltiParameters;
    }

    /**
     * @throws LtiLaunchAuthException
     */
    public function validateRoles(LtiMessagePayloadInterface $ltiMessagePayload): void
    {
        try {
            // Access the validated role collection
            $roles = $ltiMessagePayload->getValidatedRoleCollection();
            // Check if a role of type context (core or not) has been provided (o ur case for http://purl.imsglobal.org/vocab/lis/v2/membership#Learner)
            if (!$roles->canFindBy(RoleInterface::TYPE_CONTEXT)) {
                throw new LtiException('No valid IMS context role has been provided.');
            }
        } catch (Exception $e) {
            throw (
            new LtiLaunchAuthException(
                message: '[IRRECOVERABLE] ' . $e->getMessage(),
                previous: $e,
            ))->setLtiMessage($ltiMessagePayload);
        }
    }
}
