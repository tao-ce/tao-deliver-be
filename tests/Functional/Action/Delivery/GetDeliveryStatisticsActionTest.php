<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\Delivery;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\OAuth2SecurityTestingTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetDeliveryStatisticsActionTest extends WebTestCase
{
    use DocumentTestingTrait;
    use DomainTestingTrait;
    use DocumentTestingTrait;
    use OAuth2SecurityTestingTrait;

    private const GET_DELIVERY_STATISTICS_BASE_URL = '/api/v1/deliveries/%s/statistics';

    /** @var KernelBrowser */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $this->setUpTestDocumentManager();
    }

    public function testItReturnsNotFoundIfDeliveryDoesNotExist(): void
    {
        $delivery = $this->createTestDelivery();
        $this->client->request(
            Request::METHOD_GET,
            sprintf(self::GET_DELIVERY_STATISTICS_BASE_URL, 'nonExistingDeliveryId'),
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessTokenByDelivery($delivery))],
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testItReturnsDeliveryStatistics(): void
    {
        $delivery = $this->createTestDelivery();

        $deliveryExecution1 = $this->createTestDeliveryExecution('userId1#deliveryId#resultId#tenantId', $delivery->getId());
        $deliveryExecution1->setStatus(DeliveryExecution::STATUS_INITIAL);

        $deliveryExecution2 = $this->createTestDeliveryExecution('userId2#deliveryId#resultId#tenantId', $delivery->getId());
        $deliveryExecution2->setStatus(DeliveryExecution::STATUS_INTERACTING);

        $deliveryExecution3 = $this->createTestDeliveryExecution('userId3#deliveryId#resultId#tenantId', $delivery->getId());
        $deliveryExecution3->setStatus(DeliveryExecution::STATUS_CLOSED);

        $this->saveDocument($deliveryExecution1);
        $this->saveDocument($deliveryExecution2);
        $this->saveDocument($deliveryExecution3);
        $this->saveDocument($delivery);

        $this->client->request(
            Request::METHOD_GET,
            sprintf(self::GET_DELIVERY_STATISTICS_BASE_URL, $delivery->getId()),
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessTokenByDelivery($delivery))],
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $this->assertEquals(
            [
                'totalDeliveryExecutions' => 3,
                'deliveryExecutionsStatusInitial' => 1,
                'deliveryExecutionsStatusInteracting' => 1,
                'deliveryExecutionsStatusClosed' => 1,
            ],
            json_decode($response->getContent(), true)['data'],
        );
    }
}
