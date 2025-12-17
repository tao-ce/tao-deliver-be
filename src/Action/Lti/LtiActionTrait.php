<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Lti;

use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use OAT\Library\Lti1p3Core\Message\Payload\MessagePayloadInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait LtiActionTrait
{
    public function __construct(
        private readonly RegistrationRepositoryInterface $registrationRepository,
    ) {
    }

    private function getLtiRegistrationFromLtiMessagePayload(LtiMessagePayloadInterface $ltiMessagePayload): RegistrationInterface
    {
        $issuer = $ltiMessagePayload->getMandatoryClaim(MessagePayloadInterface::CLAIM_ISS);
        $audience = current($ltiMessagePayload->getMandatoryClaim(MessagePayloadInterface::CLAIM_AUD));

        return $this->getLtiRegistration($issuer, $audience);
    }

    private function getLtiRegistrationFromAccessToken(LtiMessagePayloadInterface $ltiMessagePayload): RegistrationInterface
    {
        $ltiClaims = $ltiMessagePayload->getClaim('ltiClaims');

        $issuer = $ltiClaims[MessagePayloadInterface::CLAIM_ISS];
        $audience = $ltiClaims[MessagePayloadInterface::CLAIM_AUD];

        return $this->getLtiRegistration($issuer, is_array($audience) ? current($audience) : $audience);
    }

    private function getLtiRegistration(string $issuer, string $clientId): RegistrationInterface
    {
        $registration = $this->registrationRepository->findByPlatformIssuer($issuer, $clientId);

        if (!$registration) {
            throw new NotFoundHttpException(sprintf(
                'LTI registration not found with issuer "%s" and audience "%s"',
                $issuer,
                $clientId,
            ));
        }

        return $registration;
    }
}
