<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\Delivery;

use App\Domain\Delivery\Model\Delivery;
use App\Service\Delivery\GenerateDeliveryLtiLaunchUrlService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\OAuth2SecurityTestingTrait;
use OAT\Bundle\DocumentManagerBundle\Manager\DocumentManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetDeliveryActionTest extends WebTestCase
{
    use DomainTestingTrait;
    use LoggerTestingTrait;
    use OAuth2SecurityTestingTrait;

    private const GET_DELIVERY_BASE_URL = '/api/v1/deliveries/';

    /** @var KernelBrowser */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $this->setUpTestLogHandler();
    }

    public function testItReturnsDelivery(): void
    {
        $delivery = $this->createTestDelivery();

        $documentManagerClass = static::getContainer()->get(DocumentManagerInterface::class);

        $repository = $documentManagerClass->getRepositoryForClass(Delivery::class);
        $repository->save($delivery);

        $this->client->request(
            Request::METHOD_GET,
            self::GET_DELIVERY_BASE_URL . $delivery->getId(),
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessTokenByDelivery($delivery))],
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true)['data'];

        $this->assertEquals($delivery->getId(), $data['id']);
        $this->assertEquals($delivery->getTenantId(), $data['tenantId']);
        $this->assertEquals($delivery->getQtiCompactTestFilePath(), $data['compactTestFilePath']);
        $this->assertEquals($delivery->getConfiguration(), $data['configuration']);
        $this->assertEquals(
            static::getContainer()->get(GenerateDeliveryLtiLaunchUrlService::class)->generate($delivery),
            $data['launchUrl'],
        );
    }

    public function testItReturnsNotFoundIfDeliveryDoesNotExist(): void
    {
        $this->client->request(
            Request::METHOD_GET,
            self::GET_DELIVERY_BASE_URL . 'nonExistingDeliveryId',
            [],
            [],
            [],
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }
}
