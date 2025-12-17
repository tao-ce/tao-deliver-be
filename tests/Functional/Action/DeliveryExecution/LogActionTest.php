<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Messenger\Message\DeliveryExecution\ExecutionLogMessage;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Tests\Traits\CacheTestingTrait;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use App\Tests\Traits\OAuth2SecurityTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Carbon\Carbon;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LogActionTest extends WebTestCase
{
    use CacheTestingTrait;
    use DocumentTestingTrait;
    use DomainTestingTrait;
    use MessengerTestingTrait;
    use OAuth2SecurityTestingTrait;
    use QtiTestingTrait;

    private const NOW = '2024-03-14T17:20:00+00:00';
    private const TRANSPORT_QUEUE_NAME = 'delivery-execution-assessment-log';

    private const TEST_URL = '/api/v1/delivery-executions/%s/log';

    private KernelBrowser $client;

    public function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::NOW);

        $this->client = static::createClient();

        $this->setUpTestDocumentManager();
        $this->setUpTestMessageBus();
        $this->setUpTestCache();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow();
    }


    public function testReturnsResponsesForActions(): void
    {
        $expectedResponse = [
            'success' => true,
        ];

        $deliveryExecution = $this->createDeliveryExecution(DeliveryExecution::STATUS_INTERACTING);

        $response = $this->sendRequest($deliveryExecution, 'deliver-fe', 'test message');

        $data = json_decode($response->getContent(), true);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_ACCEPTED, $response->getStatusCode());

        $this->assertEquals($expectedResponse, $data);
    }

    public function testMessageIsCreatedCorrect(): void
    {
        $deliveryExecution = $this->createDeliveryExecution(DeliveryExecution::STATUS_INTERACTING);
        $this->sendRequest($deliveryExecution, 'deliver-fe', 'test message');

        $this->assertCountTransportMessages(self::TRANSPORT_QUEUE_NAME, 1);

        /** @var ExecutionLogMessage $message **/
        $this->assertJsonStringEqualsJsonString(
            json_encode(
                [
                    'action' => [
                        'status' => 'interacting',
                        'type' => 'log',
                    ],
                    'actorIdentity' => [
                        'id' => 'userId',
                        'ip' => '127.0.0.1',
                        'name' => 'Test Taker',
                        'role' => 'deliver-fe',
                        'userAgent' => 'Symfony BrowserKit',
                    ],
                    'deliveryExecution' => [
                        'id' => $deliveryExecution->getId(),
                        'status' => 'interacting',
                    ],
                    'itemId' => 'Item-Q01',
                    'reason' => [
                        'code' => 40999,
                        'message' => '[deliver-fe] test message',
                    ],
                    'resourceLink' => [
                        'identifier' => '',
                    ],
                    'timestamp' => Carbon::now()->getTimestampMs(),
                ],
            ),
            json_encode($this->getTransportMessages(self::TRANSPORT_QUEUE_NAME)[0]->getMessage()),
        );
    }

    public function testItRejectsCrossSessionRequest(): void
    {
        $deliveryExecution = $this->createDeliveryExecution(DeliveryExecution::STATUS_INTERACTING);

        $response = $this->sendRequest($deliveryExecution, 'deliver-fe', 'test message', "anotherUser_{$deliveryExecution->getId()}");

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testLogNotSaveForClosedDelivery(): void
    {
        $deliveryExecution = $this->createDeliveryExecution(DeliveryExecution::STATUS_CLOSED);

        $response = $this->sendRequest($deliveryExecution, 'deliver-fe', 'test message');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_ACCEPTED, $response->getStatusCode());
    }

    public function testNotAllowedIssuerGiven(): void
    {
        $deliveryExecution = $this->createDeliveryExecution(DeliveryExecution::STATUS_INTERACTING);

        $response = $this->sendRequest($deliveryExecution, 'incorrectIssuer', 'test message');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testEmptyReasonGiven(): void
    {
        $deliveryExecution = $this->createDeliveryExecution(DeliveryExecution::STATUS_INTERACTING);

        $response = $this->sendRequest($deliveryExecution, 'realtime', '');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    private function sendRequest(
        DeliveryExecution $deliveryExecution,
        string $issuer,
        string $reason,
        ?string $accessTokenDeliveryExecution = null,
    ): Response {
        $accessTokenDeliveryExecution = $accessTokenDeliveryExecution ?? $deliveryExecution->getId();
        $this->client->request(
            method: Request::METHOD_POST,
            uri: sprintf(self::TEST_URL, urlencode($deliveryExecution->getId())),
            server: [
                'HTTP_AUTHORIZATION' => sprintf(
                    'Bearer %s',
                    $this->createOAuth2AccessToken($accessTokenDeliveryExecution),
                ),
            ],
            content: json_encode([
                'issuer' => $issuer,
                'reason' => $reason,
            ]),
        );

        return $this->client->getResponse();
    }

    private function createDeliveryExecution(string $status): DeliveryExecution
    {
        $this->copyCompiledTestToStorage([
            'compact-test.xml',
            'Item-Q01/item.json',
            'Item-Q02/item.json',
            'Item-Q03/item.json',
        ]);

        $deliveryExecution = $this->createTestDeliveryExecution(
            'dIresu#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            ['user_id' => 'userId', 'user_name' => 'Test Taker'],
            null,
            null,
            $status,
        );

        $deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);

        $testSession = $deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);
        $testSession->beginTestSession();
        $testSession->beginAttempt();
        $deliveryExecutionPropertyService->persistTestSession($testSession);

        $this->saveDocument($deliveryExecution);

        return $deliveryExecution;
    }
}
