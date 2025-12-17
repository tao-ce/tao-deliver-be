<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\Delivery;

use App\Domain\Delivery\Model\Delivery;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\OAuth2SecurityTestingTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteDeliveryActionTest extends WebTestCase
{
    use LoggerTestingTrait;
    use DomainTestingTrait;
    use DocumentTestingTrait;
    use OAuth2SecurityTestingTrait;

    private const DELETE_DELIVERY_BASE_URL = '/api/v1/deliveries/%s';

    /** @var KernelBrowser */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $this->setUpTestLogHandler();
        $this->setUpTestDocumentManager();
    }

    public function testItMarksDeliveryAsDeleted(): void
    {
        $delivery = $this->createTestDelivery();

        $this->assertFalse($delivery->isDeleted());

        $this->saveDocument($delivery);

        $this->client->request(
            Request::METHOD_DELETE,
            sprintf(self::DELETE_DELIVERY_BASE_URL, $delivery->getId()),
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessTokenByDelivery($delivery))],
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $delivery = $this->findDocumentById(Delivery::class, $delivery->getId());

        $this->assertTrue($delivery->isDeleted());
    }

    public function testItReturnsNotFoundIfDeliveryDoesNotExist(): void
    {
        $this->client->request(
            Request::METHOD_DELETE,
            sprintf(self::DELETE_DELIVERY_BASE_URL, 'nonExistingDeliveryId'),
            [],
            [],
            [],
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }
}
