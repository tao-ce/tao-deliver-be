<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\TestRunner\Service\TestSessionInitiator;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\OAuth2SecurityTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetAttachmentsDownloadUploadUrlActionTest extends WebTestCase
{
    use DomainTestingTrait;
    use DocumentTestingTrait;
    use QtiTestingTrait;
    use OAuth2SecurityTestingTrait;

    private const BASE_URL = '/api/v1/delivery-executions/%s/attachments';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        self::ensureKernelShutdown();
        $this->client = static::createClient();

        $this->setUpTestDocumentManager();

        $this->saveDocument($this->createTestDelivery('deliveryId'));
    }

    private function invokeApiPoint(
        string $deliveryExecutionId,
        ?array $parameters = [
            'item_id' => 'item-1',
            'response_id' => 'RESPONSE',
        ],
    ): void {
        $serverParams['HTTP_AUTHORIZATION'] = "Bearer {$this->createOAuth2AccessToken($deliveryExecutionId)}";

        $this->client->request(
            Request::METHOD_POST,
            sprintf(static::BASE_URL, rawurlencode($deliveryExecutionId)),
            $parameters,
            [],
            $serverParams,
        );
    }

    public function testItResponsesSuccessful(): void
    {
        $this->copyCompiledTestToStorage([
            'compact-test.xml',
        ], 'ExtendedTextInteraction');

        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#ExtendedTextInteraction#resultId#tenantId',
            'deliveryId',
            'tenantId',
            ['user_id' => 'userId'],
            null,
            null,
            DeliveryExecution::STATUS_INTERACTING,
        );

        static::getContainer()->get(TestSessionInitiator::class)->init($deliveryExecution);

        $this->saveDocument($deliveryExecution);

        $this->invokeApiPoint($deliveryExecution->getId());

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    public function testItResponsesNotFoundIfDeliveryExecutionDoesNotExist(): void
    {
        $this->invokeApiPoint(
            'invalid_delivery_execution_id',
        );

        $this->assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testItRequiredParamsMissed(): void
    {
        $this->copyCompiledTestToStorage([
            'compact-test.xml',
        ], 'ExtendedTextInteraction');

        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#ExtendedTextInteraction#resultId#tenantId',
            'deliveryId',
            'tenantId',
            ['user_id' => 'userId'],
            null,
            null,
            DeliveryExecution::STATUS_INTERACTING,
        );

        static::getContainer()->get(TestSessionInitiator::class)->init($deliveryExecution);

        $this->saveDocument($deliveryExecution);

        $this->invokeApiPoint($deliveryExecution->getId(), []);
        $responseContent = $this->client->getResponse()->getContent();

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        $this->assertStringContainsString("[itemId]: This value should not be blank.", $responseContent);
        $this->assertStringContainsString("[responseId]: This value should not be blank", $responseContent);
    }
}
