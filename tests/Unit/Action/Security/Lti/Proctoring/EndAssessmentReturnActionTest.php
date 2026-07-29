<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Action\Security\Lti\Proctoring;

use App\Action\Security\Lti\Proctoring\EndAssessmentReturnAction;
use App\Repository\DeliveryRepository;
use App\Responder\SerializerResponder;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use App\Service\Locale\Contract\UserLocaleProviderInterface;
use App\Tests\Traits\DomainTestingTrait;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\LaunchPresentationClaim;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Proctoring\Message\Launch\Builder\EndAssessmentLaunchRequestBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class EndAssessmentReturnActionTest extends TestCase
{
    use DomainTestingTrait;

    private const DELIVER_FRONTEND_END_ASSESSMENT_URL = 'https://deliver.example.com:8443/thank-you';
    private const ORIGINAL_RETURN_URL = 'https://portal.example.com:9443/my-sessions?attempt=1';

    private LoggerInterface&MockObject $auditLogger;
    private RepositoryAwareDeliveryExecutionServiceInterface&MockObject $deliveryExecutionService;
    private DeliveryRepository&MockObject $deliveryRepository;
    private RegistrationRepositoryInterface&MockObject $registrationRepository;
    private EndAssessmentLaunchRequestBuilder&MockObject $endAssessmentLaunchRequestBuilder;
    private UserLocaleProviderInterface&MockObject $userLocaleProvider;
    private SerializerResponder&MockObject $responder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditLogger = $this->createMock(LoggerInterface::class);
        $this->deliveryExecutionService = $this->createMock(RepositoryAwareDeliveryExecutionServiceInterface::class);
        $this->deliveryRepository = $this->createMock(DeliveryRepository::class);
        $this->registrationRepository = $this->createMock(RegistrationRepositoryInterface::class);
        $this->endAssessmentLaunchRequestBuilder = $this->createMock(EndAssessmentLaunchRequestBuilder::class);
        $this->userLocaleProvider = $this->createMock(UserLocaleProviderInterface::class);
        $this->responder = $this->createMock(SerializerResponder::class);
    }

    public function testInvokeAllowsThankYouRedirectWhenNestedReturnUrlMatchesOriginalHost(): void
    {
        $thankYouUrl = sprintf(
            'https://deliver.example.com:8443/thank-you?returnUrl=%s',
            urlencode(self::ORIGINAL_RETURN_URL),
        );
        $subject = $this->createSubject();

        $response = $subject->__invoke(
            new Request(['redirectUrl' => $thankYouUrl]),
            'userId#deliveryId#resultId#tenantId',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame($thankYouUrl, $response->getTargetUrl());
    }

    public function testInvokeRejectsThankYouRedirectWhenNestedReturnUrlMatchesDifferentHost(): void
    {
        $subject = $this->createSubject();
        $thankYouUrl = 'https://deliver.example.com:8443/thank-you?returnUrl=https%3A%2F%2Fevil.example.com%2Fmy-sessions';

        $response = $subject->__invoke(
            new Request(['redirectUrl' => $thankYouUrl]),
            'userId#deliveryId#resultId#tenantId',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(self::DELIVER_FRONTEND_END_ASSESSMENT_URL, $response->getTargetUrl());
    }

    public function testInvokePreservesRedirectPortAndSortedQueryParameters(): void
    {
        $subject = $this->createSubject();
        $redirectUrl = 'https://portal.example.com:9443/my-sessions?b=2&a=1';

        $response = $subject->__invoke(
            new Request(['redirectUrl' => $redirectUrl]),
            'userId#deliveryId#resultId#tenantId',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://portal.example.com:9443/my-sessions?a=1&b=2', $response->getTargetUrl());
    }

    public function testInvokeUsesThankYouPageAsReturnUrlForProctoringLaunch(): void
    {
        $registration = $this->createMock(RegistrationInterface::class);
        $message = $this->createMock(LtiMessageInterface::class);
        $thankYouUrl = sprintf(
            'https://deliver.example.com:8443/thank-you?returnUrl=%s',
            urlencode(self::ORIGINAL_RETURN_URL),
        );

        $this->registrationRepository
            ->expects(self::once())
            ->method('findByPlatformIssuer')
            ->with('https://assessment-platform.example.com', 'client-id')
            ->willReturn($registration);

        $this->userLocaleProvider
            ->expects(self::once())
            ->method('provide')
            ->willReturn('en-US');

        $this->endAssessmentLaunchRequestBuilder
            ->expects(self::once())
            ->method('buildEndAssessmentLaunchRequest')
            ->willReturnCallback(
                function (
                    RegistrationInterface $actualRegistration,
                    string $loginHint,
                    ?string $endAssessmentUrl = null,
                    int $attemptNumber = 1,
                    ?string $deploymentId = null,
                    array $roles = [],
                    array $optionalClaims = [],
                ) use (
                    $registration,
                    $message,
                    $thankYouUrl,
                ) {
                    self::assertSame($registration, $actualRegistration);
                    self::assertJson($loginHint);
                    self::assertSame(['http://purl.imsglobal.org/vocab/lis/v2/membership#Learner'], $roles);

                    /** @var LaunchPresentationClaim $launchPresentationClaim */
                    $launchPresentationClaim = $optionalClaims[LtiMessagePayloadInterface::CLAIM_LTI_LAUNCH_PRESENTATION];
                    self::assertSame($thankYouUrl, $launchPresentationClaim->getReturnUrl());
                    self::assertSame('en-US', $launchPresentationClaim->getLocale());

                    return $message;
                },
            );

        $message
            ->expects(self::once())
            ->method('toUrl')
            ->willReturn('https://proctoring.example.com/end-assessment');

        $subject = $this->createSubject([
            'assessment_platform_issuer' => 'https://assessment-platform.example.com',
            'assessment_platform_client_id' => 'client-id',
        ]);

        $response = $subject->__invoke(
            new Request(['redirectUrl' => $thankYouUrl]),
            'userId#deliveryId#resultId#tenantId',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://proctoring.example.com/end-assessment', $response->getTargetUrl());
    }

    public function testInvokePropagatesErrorClaimsFromRedirectUrlToProctoringLaunch(): void
    {
        $registration = $this->createMock(RegistrationInterface::class);
        $message = $this->createMock(LtiMessageInterface::class);
        $redirectUrl = sprintf(
            'https://portal.example.com:9443/my-sessions?lti_errormsg=%s&lti_errorlog=%s',
            urlencode('Secure browser validation'),
            urlencode('Secure browser validation log'),
        );

        $this->registrationRepository
            ->expects(self::once())
            ->method('findByPlatformIssuer')
            ->with('https://assessment-platform.example.com', 'client-id')
            ->willReturn($registration);

        $this->userLocaleProvider
            ->expects(self::once())
            ->method('provide')
            ->willReturn('en-US');

        $this->endAssessmentLaunchRequestBuilder
            ->expects(self::once())
            ->method('buildEndAssessmentLaunchRequest')
            ->willReturnCallback(
                function (
                    RegistrationInterface $actualRegistration,
                    string $loginHint,
                    ?string $endAssessmentUrl = null,
                    int $attemptNumber = 1,
                    ?string $deploymentId = null,
                    array $roles = [],
                    array $optionalClaims = [],
                ) use (
                    $registration,
                    $message,
                ) {
                    self::assertSame($registration, $actualRegistration);
                    self::assertJson($loginHint);
                    self::assertSame(['http://purl.imsglobal.org/vocab/lis/v2/membership#Learner'], $roles);
                    self::assertSame(
                        'Secure browser validation',
                        $optionalClaims['https://purl.imsglobal.org/spec/lti-ap/claim/errormsg'] ?? null,
                    );
                    self::assertSame(
                        'Secure browser validation log',
                        $optionalClaims['https://purl.imsglobal.org/spec/lti-ap/claim/errorlog'] ?? null,
                    );

                    return $message;
                },
            );

        $message
            ->expects(self::once())
            ->method('toUrl')
            ->willReturn('https://proctoring.example.com/end-assessment');

        $subject = $this->createSubject([
            'assessment_platform_issuer' => 'https://assessment-platform.example.com',
            'assessment_platform_client_id' => 'client-id',
        ]);

        $response = $subject->__invoke(
            new Request(['redirectUrl' => $redirectUrl]),
            'userId#deliveryId#resultId#tenantId',
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://proctoring.example.com/end-assessment', $response->getTargetUrl());
    }

    private function createSubject(array $ltiLaunchParameters = []): EndAssessmentReturnAction
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            ltiLaunchParameters: array_merge(
                [
                    'launch_presentation_return_url' => self::ORIGINAL_RETURN_URL,
                    'resource_link_id' => 'resource-link-id',
                    'roles' => ['http://purl.imsglobal.org/vocab/lis/v2/membership#Learner'],
                ],
                $ltiLaunchParameters,
            ),
        );
        $delivery = $this->createTestDelivery(id: $deliveryExecution->getDeliveryId());

        $this->deliveryExecutionService
            ->method('findDeliveryExecutionOrFail')
            ->with($deliveryExecution->getId())
            ->willReturn($deliveryExecution);

        $this->deliveryRepository
            ->method('find')
            ->with($deliveryExecution->getDeliveryId())
            ->willReturn($delivery);

        return new EndAssessmentReturnAction(
            self::DELIVER_FRONTEND_END_ASSESSMENT_URL,
            $this->auditLogger,
            $this->deliveryExecutionService,
            $this->deliveryRepository,
            $this->registrationRepository,
            $this->endAssessmentLaunchRequestBuilder,
            $this->userLocaleProvider,
            $this->responder,
        );
    }
}
