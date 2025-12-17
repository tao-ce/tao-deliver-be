<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Tests\Functional\Action\Lti\DeepLinking;

use App\DynamicQueryApi\Gateway\DynamicQueryApiGateway;
use App\DynamicQueryApi\Model\Battery;
use App\DynamicQueryApi\Model\SearchResponse;
use App\Tests\Traits\JwtTestingTrait;
use Lcobucci\JWT\Configuration;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetBatteriesActionTest extends WebTestCase
{
    use JwtTestingTrait;

    private const PATH = '/api/v1/lti/deep-links/batteries';

    private DynamicQueryApiGateway|MockObject $dynamicQueryApiGatewayMock;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->initContainer();
    }

    public function testRequest(): void
    {
        $token = $this->generateJwt();
        $authorizationHeader = sprintf('Bearer %s', $token);

        $this->dynamicQueryApiGatewayMock
            ->expects($this->once())
            ->method('searchBatteries')
            ->with([
                'filters' => [
                    [
                        'field' => 'tenantId',
                        'type' => 'terms',
                        'values' => ['tenantId'],
                    ],
                ],
            ])
            ->willReturn(new SearchResponse(
                [
                    new Battery(
                        'id',
                        'name',
                        'description',
                        'mode',
                        'status',
                        'tenantId',
                        ['deliveryId1', 'deliveryId2'],
                    ),
                ],
                1,
                ['id'],
            ));

        $this->client->request(
            Request::METHOD_GET,
            self::PATH,
            server: [
                'HTTP_AUTHORIZATION' => $authorizationHeader,
            ],
        );

        $response = $this->client->getResponse();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $this->assertSame([
            'data' => [
                [
                    'id' => 'id',
                    'name' => 'name',
                    'nrOfDeliveries' => 2,
                ],
            ],
        ], json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    private function initContainer(): void
    {
        $this->mockDynamicQueryApiGateway();
    }

    private function mockDynamicQueryApiGateway(): void
    {
        $this->dynamicQueryApiGatewayMock = $this->createMock(DynamicQueryApiGateway::class);

        static::getContainer()->set(DynamicQueryApiGateway::class, $this->dynamicQueryApiGatewayMock);
    }

    private function generateJwt(): string
    {
        $configuration = Configuration::forUnsecuredSigner();
        $builder = $configuration->builder();

        $builder
            ->withClaim('tenant_id', 'tenantId');

        return $builder
            ->getToken($configuration->signer(), $configuration->signingKey())
            ->toString();
    }
}
