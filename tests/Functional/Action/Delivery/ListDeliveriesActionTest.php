<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\Delivery;

use App\Serializer\Normalizer\DeliveryNormalizer;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\OAuth2SecurityTestingTrait;
use OAT\Bundle\DocumentManagerBundle\Document\Collection\DocumentCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ListDeliveriesActionTest extends WebTestCase
{
    use LoggerTestingTrait;
    use DomainTestingTrait;
    use DocumentTestingTrait;
    use OAuth2SecurityTestingTrait;

    private const LIST_DELIVERIES_BASE_URL = '/api/v1/deliveries';

    /** @var KernelBrowser */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $this->setUpTestLogHandler();
        $this->setUpTestDocumentManager();
    }

    public function testNoDeliveriesFoundForGivenTenantId(): void
    {
        $this->client->request(
            Request::METHOD_GET,
            self::LIST_DELIVERIES_BASE_URL,
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessToken())],
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true)['data'];

        $this->assertEmpty($data);
    }

    public function testItReturnsAListOfDeliveries(): void
    {
        $tenantId = 'dev-acc.dev-ins';
        $deliveries = $this->createAndSaveMultipleDeliveries($tenantId);

        $this->client->request(
            Request::METHOD_GET,
            self::LIST_DELIVERIES_BASE_URL,
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessToken($tenantId))],
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true)['data'];

        $this->assertEquals($data, $this->normalizeDeliveries($deliveries));
    }

    public function createAndSaveMultipleDeliveries(string $tenantId): DocumentCollection
    {
        $deliveries = new DocumentCollection();

        for ($iterator = 1; $iterator < 10; $iterator++) {
            $delivery = $this->createTestDelivery(
                'id' . $iterator,
                $tenantId,
            );
            $deliveries->add($delivery);
        }
        $this->saveDocumentCollection($deliveries);

        return $deliveries;
    }

    public function normalizeDeliveries(DocumentCollection $deliveries): array
    {
        $deliveryNormalizer = static::getContainer()->get(DeliveryNormalizer::class);
        $normalizeDeliveries = [];
        foreach ($deliveries as $delivery) {
            $normalizeDeliveries[] = $deliveryNormalizer->normalize($delivery);
        }

        return $normalizeDeliveries;
    }
}
