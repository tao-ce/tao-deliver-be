<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lti;

use App\Environment\FeatureFlagAdapterInterface;
use App\Generator\UrlGenerator;
use App\Lti\Exception\LtiCustomSettingsException;
use App\Lti\LtiCustomSettings;
use App\Service\Locale\Contract\UserLocaleProviderInterface;
use App\Service\Lti\Dto\StartProctoringRequestContext;
use App\Service\Lti\LtiProctoringService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use OAT\Library\EnvironmentManagementClient\Converter\LtiRegistrationConverter;
use OAT\Library\EnvironmentManagementClient\Model\Configuration;
use OAT\Library\EnvironmentManagementClient\Model\LtiRegistration;
use OAT\Library\EnvironmentManagementClient\Model\LtiRegistrationInterface;
use OAT\Library\EnvironmentManagementClient\Model\LtiTool;
use OAT\Library\EnvironmentManagementClient\Repository\ConfigurationRepositoryInterface;
use OAT\Library\EnvironmentManagementClient\Repository\LtiRegistrationRepository;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ContextClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ResourceLinkClaim;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayload;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\User\UserIdentity;
use OAT\Library\Lti1p3Proctoring\Message\Launch\Builder\StartProctoringLaunchRequestBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LtiProctoringServiceTest extends KernelTestCase
{
    use DomainTestingTrait;
    use MessengerTestingTrait;
    use QtiTestingTrait;

    private const EXPECTED_PROCTORING_URL = 'https://proctoring.tool';
    private const EXPECTED_FRONTEND_URL = 'http://frontend-url/';
    private const EXPECTED_USER_ID = 'test';

    private bool $isAcsServiceEnabled;
    private StartProctoringLaunchRequestBuilder&MockObject $requestBuilder;
    private LtiTool&MockObject $ltiTool;
    private LtiProctoringService $subject;

    public function setUp(): void
    {
        $this->isAcsServiceEnabled = true;
        self::bootKernel();
        $this->setUpTestMessageBus();

        $this->requestBuilder = $this->createMock(StartProctoringLaunchRequestBuilder::class);
        $urlGenerator = $this->createMock(UrlGenerator::class);
        $ltiRegistrationRepository = $this->createMock(LtiRegistrationRepository::class);
        $ltiRegistrationConverter = $this->createMock(LtiRegistrationConverter::class);
        $configurationRepository = $this->createMock(ConfigurationRepositoryInterface::class);
        $userLocaleProvider = $this->createMock(UserLocaleProviderInterface::class);

        $this->ltiTool = $this->createMock(LtiTool::class);
        $ltiRegistrationMock = $this->createMock(LtiRegistration::class);
        $ltiRegistrationMock->method('getLtiTool')->willReturn($this->ltiTool);
        $registrationMock = $this->createMock(RegistrationInterface::class);
        $registrationMock
            ->method('getIdentifier')
            ->willReturn('registrationId');
        $ltiRegistrationRepository->expects(self::once())->method('find')->willReturn($ltiRegistrationMock);
        $ltiRegistrationConverter
            ->method('convert')
            ->with($ltiRegistrationMock)
            ->willReturn($registrationMock);

        $configurationMock = $this->createMock(Configuration::class);

        $configurationRepository->expects(self::once())->method('find')->willReturn($configurationMock);

        $featureFlagAdapter = $this->createMock(FeatureFlagAdapterInterface::class);
        $featureFlagAdapter
            ->method('isEnabled')
            ->with('1', 'acs.service', true)
            ->willReturnReference($this->isAcsServiceEnabled);

        $this->subject = new LtiProctoringService(
            $this->requestBuilder,
            $urlGenerator,
            $ltiRegistrationRepository,
            $ltiRegistrationConverter,
            $configurationRepository,
            $this->getContainer()->get(LtiCustomSettings::class),
            $userLocaleProvider,
            $featureFlagAdapter,
        );
    }

    public function testStartProctoringRequestInterruptedWhenInternalToolIsUsed(): void
    {
        $this->requestBuilder->expects(self::never())->method('buildStartProctoringLaunchRequest');
        $this->ltiTool->method('isInternal')->willReturn(true);
        $this->subject->getStartProctoringRequestUrl(
            $this->createStartProctoringRequestContext(),
        );
    }

    public function testStartProctoringRequestContainsAcsClaim(): void
    {
        $this->requestBuilder->method('buildStartProctoringLaunchRequest')
            ->willReturnCallback(
                function (
                    $ltiResourceLink,
                    $registration,
                    string $startAssessmentUrl,
                    string $loginHint,
                    int $attemptNumber = 1,
                    ?string $deploymentId = null,
                    array $roles = ['http://purl.imsglobal.org/vocab/lis/v2/membership#Learner'],
                    array $optionalClaims = [],
                ) {
                    $this->assertArrayHasKey('https://purl.imsglobal.org/spec/lti-ap/claim/acs', $optionalClaims);
                    return $this->createMock(LtiMessageInterface::class);
                },
            );

        $this->subject->getStartProctoringRequestUrl(
            $this->createStartProctoringRequestContext(),
        );
    }

    public function testStartProctoringRequestContainsNoAcsClaim(): void
    {
        $this->isAcsServiceEnabled = false;
        $this->requestBuilder->method('buildStartProctoringLaunchRequest')
            ->willReturnCallback(
                function (
                    $ltiResourceLink,
                    $registration,
                    string $startAssessmentUrl,
                    string $loginHint,
                    int $attemptNumber = 1,
                    ?string $deploymentId = null,
                    array $roles = ['http://purl.imsglobal.org/vocab/lis/v2/membership#Learner'],
                    array $optionalClaims = [],
                ) {
                    $this->assertArrayNotHasKey('https://purl.imsglobal.org/spec/lti-ap/claim/acs', $optionalClaims);
                    return $this->createMock(LtiMessageInterface::class);
                },
            );

        $this->subject->getStartProctoringRequestUrl(
            $this->createStartProctoringRequestContext(),
        );
    }

    /**
     * @dataProvider providerTestCustomClaims
     * @throws LtiCustomSettingsException
     * @throws LtiExceptionInterface
     */
    public function testStartProctoringRequestUrlContainRequireAuthorizationSettings(
        $customClaim,
        string $jsonResult,
    ): void {
        $self = $this;
        $this->requestBuilder->method('buildStartProctoringLaunchRequest')
            ->willReturnCallback(
                function (
                    $ltiResourceLink,
                    $registration,
                    string $startAssessmentUrl,
                    string $loginHint,
                    int $attemptNumber = 1,
                    ?string $deploymentId = null,
                    array $roles = ['http://purl.imsglobal.org/vocab/lis/v2/membership#Learner'],
                    array $optionalClaims = [],
                ) use (
                    $self,
                    $jsonResult
                ) {
                    $self->assertNotEmpty(
                        $optionalClaims[LtiMessagePayloadInterface::CLAIM_LTI_PROCTORING_SETTINGS],
                    );
                    $self->assertNotEmpty(
                        $optionalClaims[LtiMessagePayloadInterface::CLAIM_LTI_PROCTORING_SETTINGS]['data'],
                    );
                    $self->assertEquals(
                        $jsonResult,
                        $optionalClaims[LtiMessagePayloadInterface::CLAIM_LTI_PROCTORING_SETTINGS]['data'],
                    );

                    return $self->createMock(LtiMessageInterface::class);
                },
            );

        $this->subject->getStartProctoringRequestUrl(
            $this->createStartProctoringRequestContext($customClaim),
        );
    }

    public function providerTestCustomClaims(): array
    {
        return [
            [
                [
                    LtiCustomSettings::PARAM_REQUIRE_PROCTOR_AUTHORIZATION => true,
                ],
                '{"requireAuthorization":true,"forceAuthorization":false}',
            ],
            [
                [
                    LtiCustomSettings::PARAM_REQUIRE_PROCTOR_AUTHORIZATION => true,
                    LtiCustomSettings::PARAM_RESET => true,
                ],
                '{"requireAuthorization":true,"forceAuthorization":true}',
            ],
            [
                [
                    LtiCustomSettings::PARAM_REQUIRE_PROCTOR_AUTHORIZATION => true,
                    LtiCustomSettings::PARAM_FORCE_PROCTOR_AUTHORIZATION => true,
                ],
                '{"requireAuthorization":true,"forceAuthorization":true}',
            ],
        ];
    }

    private function createStartProctoringRequestContext(array $customClaim = []): StartProctoringRequestContext
    {
        $userIdentityClaim = $this->createMock(UserIdentity::class);
        $userIdentityClaim->method('getIdentifier')->willReturn(self::EXPECTED_USER_ID);

        $resourceLink = $this->createMock(ResourceLinkClaim::class);
        $resourceLink->method('getIdentifier')->willReturn(self::EXPECTED_PROCTORING_URL);

        $contextClaim = $this->createMock(ContextClaim::class);
        $contextClaim->method('getIdentifier')->willReturn('default');

        $ltiPayload = $this->createMock(LtiMessagePayload::class);

        $ltiPayload->method('getCustom')->willReturn($customClaim);
        $ltiPayload->method('getUserIdentity')->willReturn($userIdentityClaim);
        $ltiPayload->method('getResourceLink')->willReturn($resourceLink);
        $ltiPayload->method('getLaunchPresentation')->willReturn(null);
        $ltiPayload->method('getRoles')->willReturn([]);
        $ltiPayload->method('getContext')->willReturn($contextClaim);

        $delivery = $this->createTestDelivery('CompactTest');
        $deliveryExecution = $this->createTestDeliveryExecution(
            self::EXPECTED_USER_ID . '#deliveryId#resultId#tenantId',
            'CompactTest',
            'tenantId',
            ["client_id" => '1', "user_id" => 'test', 'custom' => $customClaim],
            null,
        );

        return new StartProctoringRequestContext(
            $ltiPayload,
            $deliveryExecution,
            $delivery,
            $deliveryExecution->getLtiLaunchParameters(),
        );
    }
}
