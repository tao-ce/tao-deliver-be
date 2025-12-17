<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Lti;

use DateTimeInterface;
use OAT\Library\EnvironmentManagementLtiClient\Client\LtiAgsClientInterface;
use OAT\Library\EnvironmentManagementLtiClient\Exception\LtiAgsClientException;
use OAT\Library\Lti1p3Ags\Factory\Score\ScoreFactory;
use OAT\Library\Lti1p3Ags\Voter\ScopePermissionVoter;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\AgsClaim;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use RuntimeException;

class LtiAgsScoreService
{
    public function __construct(
        private LtiAgsClientInterface $ltiAgsClient,
        private RegistrationRepositoryInterface $registrationRepository,
        private ScoreFactory $scoreFactory,
    ) {
    }

    /**
     * @throws LtiAgsClientException
     */
    public function send(
        array $ltiParameters,
        ?DateTimeInterface $timestamp,
        ?float $scoreGiven,
        ?float $scoreMaximum,
        string $activityProgressStatus,
        string $gradingProgressStatus,
    ): bool {
        $ltiParameters['ags_claim']['scope'] = $ltiParameters['ags_claim']['scope'] ?? [];
        $agsClaim = AgsClaim::denormalize($ltiParameters['ags_claim']);

        if (!ScopePermissionVoter::canWriteScore($agsClaim->getScopes())) {
            return false;
        }

        $registration = $this->registrationRepository->findByPlatformIssuer(
            $ltiParameters['platform_issuer'],
            $ltiParameters['client_id'],
        );

        if (null === $registration) {
            throw new RuntimeException('Unable to get registration id associated to LTI launch');
        }

        $score = $this->scoreFactory->create([
            'userId' => $ltiParameters['user_id'],
            'activityProgress' => $activityProgressStatus,
            'gradingProgress' => $gradingProgressStatus,
            'scoreGiven' => $scoreGiven,
            'scoreMaximum' => $scoreMaximum,
            'timestamp' => $timestamp,
        ]);

        $this->ltiAgsClient->publishScore($registration->getIdentifier(), $score, $agsClaim->getLineItemUrl());

        return true;
    }
}
