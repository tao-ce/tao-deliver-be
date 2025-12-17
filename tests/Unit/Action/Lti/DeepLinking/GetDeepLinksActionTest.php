<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\tests\Unit\Action\Lti\DeepLinking;

use App\Action\Lti\DeepLinking\GetDeepLinksAction;
use App\Action\Security\Lti\LaunchLti1p3Action;
use App\Action\Security\Lti\LaunchLti1p3BatteryAction;
use App\Generator\UuidGenerator;
use App\Service\ApplicationInfoService;
use OAT\Bundle\EnvironmentManagementClientBundle\Http\ResponseHelper;
use OAT\Library\EnvironmentManagementClient\Http\AuthorizationDetailsMarkerInterface;
use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\Launch\Validator\Result\LaunchValidationResultInterface;
use OAT\Library\Lti1p3Core\Message\Launch\Validator\Tool\ToolLaunchValidatorInterface;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\DeepLinkingSettingsClaim;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3DeepLinking\Message\Launch\Builder\DeepLinkingLaunchResponseBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class GetDeepLinksActionTest extends TestCase
{
    private UuidGenerator $uuidGeneratorMock;
    private HttpMessageFactoryInterface $httpMessageFactoryMock;
    private HttpFoundationFactoryInterface $httpFoundationFactoryMock;
    private DeepLinkingLaunchResponseBuilder $deepLinkingLaunchResponseBuilderMock;
    private RegistrationRepositoryInterface $registrationRepositoryMock;
    private ToolLaunchValidatorInterface $toolLaunchValidatorMock;
    private LaunchLti1p3Action $launchLti1p3ActionMock;
    private GetDeepLinksAction $deepLinksAction;
    private Request $request;
    private LtiMessagePayloadInterface $ltiMessagePayloadMock;
    private LaunchLti1p3BatteryAction $launchLti1p3BatteryActionMock;
    private string $tenantIdDummy;

    protected function setUp(): void
    {
        $this->tenantIdDummy = 'foobar';

        $this->request = new Request();

        $this->uuidGeneratorMock = $this->createMock(UuidGenerator::class);
        $this->httpMessageFactoryMock = $this->createMock(HttpMessageFactoryInterface::class);
        $this->httpFoundationFactoryMock = $this->createMock(HttpFoundationFactoryInterface::class);
        $this->deepLinkingLaunchResponseBuilderMock = $this->createMock(DeepLinkingLaunchResponseBuilder::class);
        $this->registrationRepositoryMock = $this->createMock(RegistrationRepositoryInterface::class);
        $this->toolLaunchValidatorMock = $this->createMock(ToolLaunchValidatorInterface::class);
        $this->launchLti1p3ActionMock = $this->createMock(LaunchLti1p3Action::class);
        $this->launchLti1p3BatteryActionMock = $this->createMock(LaunchLti1p3BatteryAction::class);
        $this->ltiMessagePayloadMock = $this->createMock(LtiMessagePayloadInterface::class);

        $psrHttpFactoryMock = $this->createMock(PsrHttpFactory::class);
        $psrHttpFactoryMock
            ->expects(self::once())
            ->method('createRequest')
            ->willReturn($this->createMock(ServerRequestInterface::class));

        $responseHelper = new ResponseHelper(
            $this->httpMessageFactoryMock,
            $this->httpFoundationFactoryMock,
            $this->createMock(AuthorizationDetailsMarkerInterface::class),
        );

        $this->deepLinksAction = new GetDeepLinksAction(
            $psrHttpFactoryMock,
            $this->uuidGeneratorMock,
            $this->createMock(ApplicationInfoService::class),
            $responseHelper,
            $this->deepLinkingLaunchResponseBuilderMock,
            $this->registrationRepositoryMock,
            $this->toolLaunchValidatorMock,
            $this->launchLti1p3ActionMock,
            $this->launchLti1p3BatteryActionMock,
            'https://foo.bar',
        );
    }

    /**
     * @throws LtiExceptionInterface
     */
    public function testInvokeWithValidationError(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('ERROR');

        $validationResultMock = $this->createMock(LaunchValidationResultInterface::class);
        $validationResultMock
            ->expects(self::once())
            ->method('hasError')
            ->willReturn(true);
        $validationResultMock
            ->expects(self::once())
            ->method('getError')
            ->willReturn('ERROR');

        $this
            ->toolLaunchValidatorMock
            ->expects(self::once())
            ->method('validatePlatformOriginatingLaunch')
            ->willReturn($validationResultMock);

        ($this->deepLinksAction)($this->request, $this->ltiMessagePayloadMock, $this->tenantIdDummy);
    }

    /**
     * @throws LtiExceptionInterface
     */
    public function testInvokeWithLtiDeliveryResourceLinkRequest(): void
    {
        $targetLinkUri = 'http://deliver.docker.localhost/api/v1/auth/launch-lti-1p3/1a5cfdced91c';

        $this->expectMessageType(LtiMessageInterface::LTI_MESSAGE_TYPE_RESOURCE_LINK_REQUEST);
        $this->expectTargetLinkUri($targetLinkUri);

        $responseDummy = new RedirectResponse($targetLinkUri);
        $this
            ->launchLti1p3ActionMock
            ->expects(self::once())
            ->method('__invoke')
            ->willReturn($responseDummy);

        self::assertEquals(
            $responseDummy,
            ($this->deepLinksAction)($this->request, $this->ltiMessagePayloadMock, $this->tenantIdDummy),
        );
    }

    /**
     * @throws LtiExceptionInterface
     */
    public function testInvokeWithLtiBatteryResourceLinkRequest(): void
    {
        $targetLinkUri = 'http://deliver.docker.localhost/api/v1/auth/launch-lti-1p3-battery/1a5cfdced91c';

        $this->expectMessageType(LtiMessageInterface::LTI_MESSAGE_TYPE_RESOURCE_LINK_REQUEST);
        $this->expectTargetLinkUri($targetLinkUri);

        $responseDummy = new RedirectResponse($targetLinkUri);
        $this
            ->launchLti1p3BatteryActionMock
            ->expects(self::once())
            ->method('__invoke')
            ->willReturn($responseDummy);

        self::assertEquals(
            $responseDummy,
            ($this->deepLinksAction)($this->request, $this->ltiMessagePayloadMock, $this->tenantIdDummy),
        );
    }

    /**
     * @throws LtiExceptionInterface
     */
    public function testInvokeWithBadLtiResourceLink(): void
    {
        $targetLinkUri = 'https://foo.bar';

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Bad claim target link uri: ' . $targetLinkUri);
        $this->expectMessageType(LtiMessageInterface::LTI_MESSAGE_TYPE_RESOURCE_LINK_REQUEST);
        $this->expectTargetLinkUri($targetLinkUri);

        $responseDummy = new RedirectResponse($targetLinkUri);

        self::assertEquals(
            $responseDummy,
            ($this->deepLinksAction)($this->request, $this->ltiMessagePayloadMock, $this->tenantIdDummy),
        );
    }

    /**
     * @throws LtiExceptionInterface
     */
    public function testInvokeWithoutClaim(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings claim is required');

        $this->expectMessageType(LtiMessageInterface::LTI_MESSAGE_TYPE_DEEP_LINKING_REQUEST);
        $this->expectClaims(['issuer', ['audience']]);
        $this->expectDeepLinkingSettings();
        $this->expectPlatformIssuer();

        self::assertEquals(
            new Response(),
            ($this->deepLinksAction)($this->request, $this->ltiMessagePayloadMock, $this->tenantIdDummy),
        );
    }

    /**
     * @dataProvider provideDeepLinkingData
     *
     * @throws LtiExceptionInterface
     */
    public function testInvokeWithLtiDeepLinkingRequest(string $method, array $query): void
    {
        $this->request->initialize($query, $query);
        $this->request->setMethod($method);

        $this->expectMessageType(LtiMessageInterface::LTI_MESSAGE_TYPE_DEEP_LINKING_REQUEST);
        $this->expectClaims(['issuer', ['audience']]);
        $this->expectDeepLinkingSettings(true);
        $this->expectPlatformIssuer();
        $this->expectUuidGenerate();

        $ltiMessageMock = $this->createMock(LtiMessageInterface::class);
        $ltiMessageMock
            ->expects(self::once())
            ->method('toUrl')
            ->willReturn('https://foo.bar');
        $this
            ->deepLinkingLaunchResponseBuilderMock
            ->expects(self::once())
            ->method('buildDeepLinkingLaunchErrorResponse')
            ->willReturn($ltiMessageMock);

        $response = ($this->deepLinksAction)($this->request, $this->ltiMessagePayloadMock, $this->tenantIdDummy);
        self::assertEquals(
            new RedirectResponse('https://foo.bar'),
            $response,
        );
    }

    /**
     * @dataProvider provideDeepLinkingErrorData
     *
     * @throws LtiExceptionInterface
     */
    public function testInvokeWithLtiDeepLinkingRequestWithError(string $method, array $query): void
    {
        $this->request->initialize($query, $query);
        $this->request->setMethod($method);

        $this->expectMessageType(LtiMessageInterface::LTI_MESSAGE_TYPE_DEEP_LINKING_REQUEST);
        $this->expectClaims(['issuer', ['audience']]);
        $this->expectDeepLinkingSettings(true);
        $this->expectPlatformIssuer();
        $this->expectUuidGenerate();

        $ltiMessageMock = $this->createMock(LtiMessageInterface::class);
        $ltiMessageMock
            ->expects(self::once())
            ->method('toUrl')
            ->willReturn('https://foo.bar');
        $this
            ->deepLinkingLaunchResponseBuilderMock
            ->expects(self::once())
            ->method('buildDeepLinkingLaunchErrorResponse')
            ->willReturn($ltiMessageMock);

        self::assertEquals(
            new RedirectResponse('https://foo.bar'),
            ($this->deepLinksAction)($this->request, $this->ltiMessagePayloadMock, $this->tenantIdDummy),
        );
    }

    protected function provideDeepLinkingData(): array
    {
        return [
            [
                'GET',
                [
                    'hideBatteries' => [],
                    'hideDeliveries' => [],
                    'id_token' => 'token',
                ],
            ],
            [
                'POST',
                [
                    'hideBatteries' => [],
                    'hideDeliveries' => [],
                    'id_token' => 'token',
                ],
            ],
        ];
    }

    protected function provideDeepLinkingErrorData(): array
    {
        return [
            [
                'GET',
                [
                    'hideBatteries' => [],
                    'hideDeliveries' => [],
                ],
            ],
            [
                'POST',
                [
                    'hideBatteries' => [],
                    'hideDeliveries' => [],
                ],
            ],
            [
                'PUT',
                [
                    'hideBatteries' => [],
                    'hideDeliveries' => [],
                ],
            ],
            [
                'DELETE',
                [
                    'hideBatteries' => [],
                    'hideDeliveries' => [],
                ],
            ],
        ];
    }

    private function expectMessageType(string $messageType): void
    {
        $this
            ->ltiMessagePayloadMock
            ->expects(self::once())
            ->method('getMessageType')
            ->willReturn($messageType);
    }

    private function expectClaims(array $claims): void
    {
        $this
            ->ltiMessagePayloadMock
            ->expects(self::exactly(count($claims)))
            ->method('getMandatoryClaim')
            ->willReturnOnConsecutiveCalls(...$claims);
    }

    private function expectDeepLinkingSettings(bool $mock = false): void
    {
        $this
            ->ltiMessagePayloadMock
            ->expects(self::once())
            ->method('getDeepLinkingSettings')
            ->willReturn($mock ? $this->createMock(DeepLinkingSettingsClaim::class) : null);
    }

    private function expectPlatformIssuer(): void
    {
        $this
            ->registrationRepositoryMock
            ->expects(self::once())
            ->method('findByPlatformIssuer')
            ->willReturn($this->createMock(RegistrationInterface::class));
    }

    private function expectUuidGenerate(): void
    {
        $this
            ->uuidGeneratorMock
            ->expects(self::once())
            ->method('generate')
            ->willReturn('uuid');
    }

    private function expectTargetLinkUri(string $targetLinkUri): void
    {
        $this
            ->ltiMessagePayloadMock
            ->expects(self::once())
            ->method('getTargetLinkUri')
            ->willReturn($targetLinkUri);
    }
}
