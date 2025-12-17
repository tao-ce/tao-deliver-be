<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\Lti\DeepLinking;

use App\Generator\UuidGenerator;
use App\Tests\Traits\JwtTestingTrait;
use App\Tests\Traits\RegistrationRepositoryTestingTrait;
use Exception;
use Lcobucci\JWT\Configuration;
use OAT\Library\Lti1p3Core\Message\Launch\Validator\Result\LaunchValidationResult;
use OAT\Library\Lti1p3Core\Message\Launch\Validator\Tool\ToolLaunchValidatorInterface;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetDeepLinksActionTest extends WebTestCase
{
    use JwtTestingTrait;
    use RegistrationRepositoryTestingTrait;

    private const PATH = '/api/v1/lti/deep-links';

    private ToolLaunchValidatorInterface|MockObject $toolLaunchValidatorMock;
    private UuidGenerator|MockObject $uuidGeneratorMock;
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client = static::createClient();

        $this->initContainer();
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testRequest(string $method): void
    {
        $token = $this->generateJWTWithLtiClaims();
        $params = [
            'id_token' => $token,
            'state' => $token,
        ];

        $this->client->request(
            $method,
            sprintf(
                '%s?%s',
                self::PATH,
                $method === Request::METHOD_GET ? http_build_query($params) : '',
            ),
            parameters: $method === Request::METHOD_POST ? $params : [],
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode(), $response->getContent());
        $this->assertSame('https://deep-linking.frontend.url?sessionId=uuid', $response->headers->get('Location'));
        $this->assertSame(json_encode([
            'clientId' => 'test',
            'refreshTokenId' => 'uuid',
            'userIdentifier' => null,
            'userRole' => null,
            'cookieDomain' => 'tao_deliver_be_nginx',
            'ltiToken' => $token,
            'mode' => 'cookie',
            'storagePrefix' => null,
        ], JSON_THROW_ON_ERROR), $response->headers->get('X-OAT-WITH-AUTH-DETAILS'));
    }

    public function requestMethodProvider(): array
    {
        return [
            ['GET'],
            ['POST'],
        ];
    }

    public function testRequestWithoutDeepLinkingClaim(): void
    {
        $token = $this->generateJWTWithLtiClaims(false);

        $this->client->request(
            Request::METHOD_POST,
            self::PATH,
            parameters: [
                'id_token' => $token,
                'state' => $token,
            ],
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), $response->getContent());
        $this->assertStringContainsString(
            json_encode(sprintf(
                '%s claim is required',
                LtiMessagePayloadInterface::CLAIM_LTI_DEEP_LINKING_SETTINGS,
            ), JSON_THROW_ON_ERROR),
            $response->getContent(),
        );
    }

    public function testRequestWhenErrorHappens(): void
    {
        $token = $this->generateJWTWithLtiClaims();

        $this->uuidGeneratorMock
            ->method('generate')
            ->willThrowException(new Exception('reason'));

        $this->client->request(
            Request::METHOD_POST,
            self::PATH,
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode(), $response->getContent());
        $this->assertStringStartsWith('https://return.url?JWT=', $response->headers->get('Location'));

        // get JWT query param's payload
        $parsedUrl = parse_url($response->headers->get('Location'));
        parse_str($parsedUrl['query'], $queryParams);
        $jwtQueryParam = $queryParams['JWT'];
        $jwtQueryParamPayload = $this->getJwtNormalizedPayload($jwtQueryParam);

        $this->assertArrayHasKey(LtiMessagePayloadInterface::CLAIM_LTI_DEEP_LINKING_CONTENT_ITEMS, $jwtQueryParamPayload);
        $this->assertCount(0, $jwtQueryParamPayload[LtiMessagePayloadInterface::CLAIM_LTI_DEEP_LINKING_CONTENT_ITEMS]);

        $this->assertArrayHasKey(LtiMessagePayloadInterface::CLAIM_LTI_DEEP_LINKING_ERROR_MESSAGE, $jwtQueryParamPayload);
        $this->assertSame('reason', $jwtQueryParamPayload[LtiMessagePayloadInterface::CLAIM_LTI_DEEP_LINKING_ERROR_MESSAGE]);

        $this->assertArrayHasKey(LtiMessagePayloadInterface::CLAIM_LTI_DEEP_LINKING_ERROR_LOG, $jwtQueryParamPayload);
        $this->assertSame('reason', $jwtQueryParamPayload[LtiMessagePayloadInterface::CLAIM_LTI_DEEP_LINKING_ERROR_LOG]);
    }

    private function initContainer(): void
    {
        $this->mockRegistrationRepository();
        $this->mockToolLaunchValidator();
        $this->mockUuidGenerator();
    }

    private function mockToolLaunchValidator(): void
    {
        $this->toolLaunchValidatorMock = $this->createMock(ToolLaunchValidatorInterface::class);
        $this->toolLaunchValidatorMock
            ->method('validatePlatformOriginatingLaunch')
            ->willReturn(new LaunchValidationResult());

        static::getContainer()->set(ToolLaunchValidatorInterface::class, $this->toolLaunchValidatorMock);
    }

    private function mockUuidGenerator(): void
    {
        $this->uuidGeneratorMock = $this->createMock(UuidGenerator::class);
        $this->uuidGeneratorMock
            ->method('generate')
            ->willReturn('uuid');

        static::getContainer()->set(UuidGenerator::class, $this->uuidGeneratorMock);
    }

    private function generateJWTWithLtiClaims(bool $withDeepLinkingSettingsClaim = true): string
    {
        $configuration = Configuration::forUnsecuredSigner();
        $builder = $configuration->builder();

        $builder
            ->permittedFor('test')
            ->issuedBy('test')
            ->withClaim(LtiMessagePayloadInterface::CLAIM_LTI_VERSION, '1.3.0')
            ->withClaim(LtiMessagePayloadInterface::CLAIM_LTI_MESSAGE_TYPE, 'LtiDeepLinkingRequest')
            ->withClaim(LtiMessagePayloadInterface::CLAIM_LTI_DEPLOYMENT_ID, 'deploymentId')
            ->withClaim(LtiMessagePayloadInterface::CLAIM_LTI_TARGET_LINK_URI, sprintf('https://backend.url%s', self::PATH))
            ->withClaim(LtiMessagePayloadInterface::CLAIM_LTI_ROLES, [])
            ->withClaim('tenant_id', 'tenantId');

        if ($withDeepLinkingSettingsClaim) {
            $builder->withClaim(LtiMessagePayloadInterface::CLAIM_LTI_DEEP_LINKING_SETTINGS, [
                'deep_link_return_url' => 'https://return.url',
                'accept_types' => ['ltiResourceLink'],
                'accept_presentation_document_targets' => ['iframe'],
                'accept_multiple' => true,
                'auto_create' => false,
                'data' => 'data',
            ]);
        }

        return $builder
            ->getToken($configuration->signer(), $configuration->signingKey())
            ->toString();
    }
}
