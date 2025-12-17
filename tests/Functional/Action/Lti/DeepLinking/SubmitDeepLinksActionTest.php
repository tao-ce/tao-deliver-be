<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\Lti\DeepLinking;

use App\DynamicQueryApi\Gateway\DynamicQueryApiGateway;
use App\DynamicQueryApi\Model\Battery;
use App\DynamicQueryApi\Model\SearchResponse;
use App\Lti\DeepLinking\Builder\ResourceCollectionBuilder;
use App\Tests\Traits\JwtTestingTrait;
use Exception;
use Lcobucci\JWT\Configuration;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use OAT\Library\Lti1p3Core\Platform\Platform;
use OAT\Library\Lti1p3Core\Registration\Registration;
use OAT\Library\Lti1p3Core\Registration\RegistrationRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\Key\Key;
use OAT\Library\Lti1p3Core\Security\Key\KeyChain;
use OAT\Library\Lti1p3Core\Tool\Tool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SubmitDeepLinksActionTest extends WebTestCase
{
    use JwtTestingTrait;

    private const PATH = '/api/v1/lti/deep-links/submit';

    private string $token;
    private DynamicQueryApiGateway|MockObject $dynamicQueryApiGatewayMock;
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client = static::createClient();
        $this->token = $this->generateJWTWithLtiClaims();
    }

    public function testRequest(): void
    {
        $this->initContainer();

        $this->client->request(
            Request::METHOD_POST,
            self::PATH,
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->token),
            ],
            content: json_encode([
                'batteries' => ['batteryId'],
            ], JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('url', $content);
        $this->assertStringStartsWith('https://return.url?JWT=', $content['url']);

        // get JWT query param's payload
        $parsedUrl = parse_url($content['url']);
        parse_str($parsedUrl['query'], $queryParams);
        $jwtQueryParam = $queryParams['JWT'];
        $jwtQueryParamPayload = $this->getJwtNormalizedPayload($jwtQueryParam);

        $this->assertArrayHasKey(LtiMessagePayloadInterface::CLAIM_LTI_DEEP_LINKING_CONTENT_ITEMS, $jwtQueryParamPayload);
        $this->assertCount(1, $jwtQueryParamPayload[LtiMessagePayloadInterface::CLAIM_LTI_DEEP_LINKING_CONTENT_ITEMS]);
        $this->assertSame('ltiResourceLink', $jwtQueryParamPayload[LtiMessagePayloadInterface::CLAIM_LTI_DEEP_LINKING_CONTENT_ITEMS][0]['type']);
        $this->assertSame('http://tao_deliver_be_nginx/api/v1/auth/launch-lti-1p3-battery/id', $jwtQueryParamPayload[LtiMessagePayloadInterface::CLAIM_LTI_DEEP_LINKING_CONTENT_ITEMS][0]['url']);
    }

    public function testRequestWithoutDeepLinkingClaim(): void
    {
        $this->token = $this->generateJWTWithLtiClaims(false);
        $this->initContainer();

        $this->client->request(
            Request::METHOD_POST,
            self::PATH,
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->generateJWTWithLtiClaims(false)),
            ],
            content: json_encode([
                'batteries' => ['batteryId'],
            ], JSON_THROW_ON_ERROR),
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
        $this->initContainer();

        $resourceCollectionBuilderMock = $this->createMock(ResourceCollectionBuilder::class);
        $resourceCollectionBuilderMock
            ->method('withBatteries')
            ->willThrowException(new Exception('reason'));

        static::getContainer()->set(ResourceCollectionBuilder::class, $resourceCollectionBuilderMock);

        $this->client->request(
            Request::METHOD_POST,
            self::PATH,
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->token),
            ],
            content: json_encode([
                'batteries' => ['batteryId'],
            ], JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('url', $content);
        $this->assertStringStartsWith('https://return.url?JWT=', $content['url']);

        // get JWT query param's payload
        $parsedUrl = parse_url($content['url']);
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
        $this->mockDynamicQueryApiGateway();
    }

    private function mockRegistrationRepository(): void
    {
        $privateKey = file_get_contents(__DIR__ . '/../../../../Resources/config/keys/private.key');
        $publicKey = file_get_contents(__DIR__ . '/../../../../Resources/config/keys/public.key');

        $registrationRepositoryMock = $this->createMock(RegistrationRepositoryInterface::class);
        $registrationRepositoryMock
            ->method('findByPlatformIssuer')
            ->willReturn(new Registration(
                'identifier',
                'clientId',
                new Platform('platformIdentifier', 'platformName', 'platformAudience'),
                new Tool('toolIdentifier', 'toolName', 'toolAudience', 'toolOidcInitiationUrl'),
                ['deploymentId'],
                new KeyChain('platformKeyChainIdentifier', 'platformKeyChainKeySetName', new Key($publicKey), new Key($privateKey)),
                new KeyChain('toolKeyChainIdentifier', 'toolKeyChainKeySetName', new Key($publicKey), new Key($privateKey)),
            ));

        static::getContainer()->set(RegistrationRepositoryInterface::class, $registrationRepositoryMock);
    }

    private function mockDynamicQueryApiGateway(): void
    {
        $this->dynamicQueryApiGatewayMock = $this->createMock(DynamicQueryApiGateway::class);

        $this->dynamicQueryApiGatewayMock
            ->method('searchBatteriesWithIds')
            ->with('batteryId')
            ->willReturn(new SearchResponse(
                [
                    new Battery(
                        'id',
                        'name',
                        'description',
                        'mode',
                        'status',
                        'tenantId',
                        ['deliveryId'],
                    ),
                ],
                1,
                ['id'],
            ));

        static::getContainer()->set(DynamicQueryApiGateway::class, $this->dynamicQueryApiGatewayMock);
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
            ->withClaim(LtiMessagePayloadInterface::CLAIM_LTI_TARGET_LINK_URI, 'https://backend.url/api/v1/lti/deep-links')
            ->withClaim(LtiMessagePayloadInterface::CLAIM_LTI_ROLES, [])
            ->withClaim('tenant_id', 'tenantId')
            ->withClaim('registration_id', 'registrationId')
            ->withClaim('ltiClaims', [
                'iss' => 'iss',
                'aud' => 'audi',
            ]);

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
