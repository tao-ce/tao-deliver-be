<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\Publication;

use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetPublicationActionTest extends WebTestCase
{
    use DomainTestingTrait;
    use DocumentTestingTrait;

    /** @var KernelBrowser */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $this->setUpTestDocumentManager();
    }

    public function testItReturnsPublicationRepresentation(): void
    {
        $publication = $this->createTestPublication();
        $this->saveDocument($publication);

        $uri = $this->createPublicationUri($publication->getId());

        $this->client->request(
            Request::METHOD_GET,
            $uri,
            [],
            [],
            [],
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $content);

        $data = $content['data'];
        $this->assertEquals($publication->getId(), $data['id']);
        $this->assertEquals($publication->getDeliveryId(), $data['deliveryId']);
        $this->assertEquals($publication->getTenantId(), $data['tenantId']);
        $this->assertEquals($publication->getStatus(), $data['status']);
        $this->assertEquals('http://localhost/api/v1/publications/id', $data['url']);
        $this->assertEquals($publication->getReports(), $data['reports']);
    }

    public function testItReturnsNotFoundIfPublicationDoesNotExist(): void
    {
        $this->client->request(
            Request::METHOD_GET,
            $this->createPublicationUri('invalid_publication_id'),
            [],
            [],
            [],
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    private function createPublicationUri(string $id): string
    {
        return sprintf('/api/v1/publications/%s', $id);
    }
}
