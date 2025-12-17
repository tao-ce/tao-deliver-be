<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\Sender;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use App\Messenger\Message\ResultExtractionMessage;
use OAT\Library\EnvironmentManagementClient\Exception\EnvironmentManagementClientException;
use OAT\Library\EnvironmentManagementLtiClient\Client\LtiBasicOutcomeClientInterface;
use OAT\Library\EnvironmentManagementLtiClient\Exception\LtiBasicOutcomeClientException;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Twig\Environment;

class LtiBasicOutcomeSender implements LtiResultSenderInterface
{
    public function __construct(
        private RegistrationRepositoryInterface $registrationRepository,
        private LtiBasicOutcomeClientInterface $ltiBasicOutcomeClient,
        private LtiCustomSettings $ltiCustomSettings,
        private Environment $twig,
        private LoggerInterface $auditPlatformLogger,
    ) {
    }

    public function send(DeliveryExecution $deliveryExecution, array $resultData, ResultExtractionMessage $message): void
    {
        $ltiParameters = $deliveryExecution->getLtiLaunchParameters();
        $url = $ltiParameters['lis_outcome_service_url'] ?? '';
        $registration = $ltiParameters['oauth_consumer_key'] ?? '';
        $platformIssuer = $ltiParameters['platform_issuer'] ?? '';
        $clientId = $ltiParameters['client_id'] ?? '';

        if (empty($url) || (empty($registration) && (empty($platformIssuer) || empty($clientId)))) {
            return;
        }

        $this->auditPlatformLogger->info(
            sprintf('[%s] Url was validated, URL: %s', $deliveryExecution->getOriginalId(), $url),
        );
        $xmlContent = $this->twig->render('Ims/Result/deliveryExecutionResult.xml.twig', [
            'messageIdentifier' => $message->getId(),
            'lisResultSourcedId' => $deliveryExecution->getResultId(),
            'score' => $resultData['score'],
        ]);

        try {
            $basicClientId = $clientId;
            // If the custom client id is set, we try to get it from the custom parameters and use it to find the registration
            if ($this->ltiCustomSettings->getOutcomeServiceClientId($ltiParameters)) {
                $basicClientId = $this->ltiCustomSettings->getOutcomeServiceClientId($ltiParameters);
            }
            $registration = $this->registrationRepository->findByPlatformIssuer($this->parseAudience($url), $basicClientId);
        } catch (EnvironmentManagementClientException) {
            $registration = $this->registrationRepository->findByPlatformIssuer($platformIssuer, $clientId);
        }

        try {
            $this->ltiBasicOutcomeClient->sendBasicOutcome(
                $registration->getIdentifier(),
                $url,
                $xmlContent,
            );
            $this->auditPlatformLogger->info(
                sprintf('[%s] LTI replace result request was done to %s', $deliveryExecution->getOriginalId(), $url),
            );
        } catch (LtiBasicOutcomeClientException $e) {
            throw new RuntimeException(
                sprintf(
                    'LTI replaceResultRequest failed. Delivery Execution ID: %s',
                    $deliveryExecution->getOriginalId(),
                ),
                $e->getCode(),
                $e,
            );
        }
    }

    private function parseAudience(string $url): string
    {
        $urlParts = parse_url($url);

        $audience = sprintf('%s://%s', $urlParts['scheme'] ?? 'https', $urlParts['host'] ?? '');
        $audience .= !empty($urlParts['port']) ? ':' . $urlParts['port'] : '';

        return $audience;
    }
}
