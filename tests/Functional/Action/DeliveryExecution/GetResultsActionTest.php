<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\DeliveryExecution;

use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use League\Flysystem\FilesystemWriter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetResultsActionTest extends WebTestCase
{
    use DomainTestingTrait;
    use DocumentTestingTrait;

    private const BASE_URL = '/api/v1/delivery-executions/%s/results';


    private KernelBrowser $client;
    private FilesystemWriter $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $this->setUpTestDocumentManager();

        $this->storage = static::getContainer()->get('delivery_execution_result.storage');
    }

    public function testItResponsesSuccessful(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#deliveryId#resultId#tenantId',
            'deliveryId',
            '1',
            [],
            'lis',
        );

        $this->saveDocument($deliveryExecution);

        $assessmentContent = '<xml/>';
        $this->storage->write($this->normalizeResultId($deliveryExecution->getId()), $assessmentContent);

        $this->doRequest($deliveryExecution->getId());

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('<xml/>', $this->client->getInternalResponse()->getContent());
    }

    public function testItReturnsNotFoundWhenResultIsNotAvailable(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(tenantId: '1');

        $this->saveDocument($deliveryExecution);

        $this->doRequest($deliveryExecution->getId());

        $this->assertEquals(
            Response::HTTP_NOT_FOUND,
            $this->client->getResponse()->getStatusCode(),
        );
    }

    public function testItReturnsNotFoundWhenDeliveryExecutionIdIsNotProvided(): void
    {
        $this->doRequest('');

        $this->assertEquals(
            Response::HTTP_NOT_FOUND,
            $this->client->getResponse()->getStatusCode(),
        );
    }

    private function doRequest(string $deliveryExecutionId): void
    {
        $this->client->request(
            Request::METHOD_GET,
            sprintf(static::BASE_URL, rawurlencode($deliveryExecutionId)),
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ0ZW5hbnRfaWQiOiJ0ZW5hbnRJZCJ9.7k4GPBCd7q_qX4jIcpu5ObOczdfcG3S5FQJmun2cWUw'],
        );
    }

    private function normalizeResultId(string $resultId): string
    {
        return preg_replace('~[/\\\\]~', '_', $resultId) . '.xml';
    }
}
