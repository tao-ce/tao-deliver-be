<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\OAuth2SecurityTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProcessActionsActionTest extends WebTestCase
{
    use DocumentTestingTrait;
    use DomainTestingTrait;
    use OAuth2SecurityTestingTrait;
    use QtiTestingTrait;

    private const TEST_URL = '/api/v1/delivery-executions/%s/actions';

    /** @var KernelBrowser */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $this->copyCompiledTestToStorage();
        $this->setUpTestDocumentManager();
    }


    public function testReturnsResponsesForActions(): void
    {
        $expectedResponse = [
            'success' => true,
            'errorCode' => null,
            'errorMessage' => null,
            'responses' => [
                [
                    [
                        'success' => true,
                        'name' => 'up',
                        'id' => 'up_123',
                        'errorCode' => null,
                        'errorMessage' => null,
                        'values' => [],
                    ],
                ],
            ],
        ];

        $deliveryExecution = $this->createTestDeliveryExecution(
            deliveryId: 'Basic',
            status: DeliveryExecution::STATUS_INTERACTING,
        );

        $this->saveDocument($deliveryExecution);

        $actions = [
            [
                'name' => 'up',
                'id' => 'up_123',
                'timestamp' => time(),
                'parameters' => [],
            ],
        ];

        $response = $this->sendRequest($deliveryExecution, $actions);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals($expectedResponse, $data);
    }

    public function testReturnsError500WhenUnexpectedExceptionIsThrown(): void
    {
        $expectedResponse = [
            'success' => true,
            'errorCode' => null,
            'errorMessage' => null,
            'responses' => [
                [
                    [
                        'success' => false,
                        'name' => 'invalid_action',
                        'id' => 'invalid_action_123',
                        'errorCode' => 0,
                        'errorMessage' => 'No action processor found for action name: invalid_action',
                        'values' => [],
                    ],
                ],
            ],
        ];

        $deliveryExecution = $this->createTestDeliveryExecution();

        $this->saveDocument($deliveryExecution);

        $actions = [
            [
                'name' => 'invalid_action',
                'id' => 'invalid_action_123',
                'timestamp' => time(),
                'parameters' => [],
            ],
        ];

        $response = $this->sendRequest($deliveryExecution, $actions);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals($expectedResponse, $data);
    }

    public function testExposeCorrectErrorIfIncorrectLogActionProvided(): void
    {
        $expectedResponse = [
            'success' => false,
            'errorCode' => 0,
            'errorMessage' => '[events]: This value should not be blank.',
            'responses' => [],
        ];

        $deliveryExecution = $this->createTestDeliveryExecution(status: DeliveryExecution::STATUS_INTERACTING);

        $this->saveDocument($deliveryExecution);

        $actions = [
            [
                'name' => 'ui-log',
                'id' => 'ui_log',
                'timestamp' => time(),
                'parameters' => [
                    'events' => [],
                ],
            ],
        ];

        $response = $this->sendRequest($deliveryExecution, $actions);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals($expectedResponse, $data);
    }

    public function testReturnsError400WhenSpecialCharactersAreProvided(): void
    {
        $expectedError = '[0][message][actions][0][id]: Only alpha characters, _ and - are allowed, [0][message][actions][0][name]: Only alpha characters, _ and - are allowed';

        $deliveryExecution = $this->createTestDeliveryExecution();

        $this->saveDocument($deliveryExecution);

        $actions = [
            [
                'name' => 'up/><img src=x>',
                'id' => 'up_123/><img src=x>',
                'timestamp' => time(),
                'parameters' => [],
            ],
        ];

        $response = $this->sendRequest($deliveryExecution, $actions);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responsePayload = json_decode($response->getContent(), true);
        $this->assertEquals($expectedError, $responsePayload['errorMessage']);
    }

    public function testItRejectsCrossSessionRequest(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            deliveryId: 'Basic',
            status: DeliveryExecution::STATUS_INTERACTING,
        );

        $this->saveDocument($deliveryExecution);

        $actions = [
            [
                'name' => 'up',
                'id' => 'up_123',
                'timestamp' => time(),
                'parameters' => [],
            ],
        ];

        $response = $this->sendRequest($deliveryExecution, $actions, "anotherUser_{$deliveryExecution->getId()}");

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testItRejectsCrossSessionRequestFromAnonymousUser(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            deliveryId: 'Basic',
            status: DeliveryExecution::STATUS_INTERACTING,
        );

        $this->saveDocument($deliveryExecution);

        $actions = [
            [
                'name' => 'up',
                'id' => 'up_123',
                'timestamp' => time(),
                'parameters' => [],
            ],
        ];

        $response = $this->sendRequest(
            $deliveryExecution,
            $actions,
            preg_replace('/^[^#]+/', strrev('anonymous'), $deliveryExecution->getId()),
        );

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    private function sendRequest(
        DeliveryExecution $deliveryExecution,
        array $actionRequestParameters,
        string $accessTokenId = '',
    ): Response {
        $accessTokenId = $accessTokenId ?: $deliveryExecution->getId();

        $this->client->request(
            Request::METHOD_POST,
            sprintf(self::TEST_URL, urlencode($deliveryExecution->getId())),
            server: [
                'HTTP_AUTHORIZATION' => sprintf(
                    'Bearer %s',
                    $this->createOAuth2AccessToken($accessTokenId),
                ),
            ],
            content: json_encode(
                [
                    [
                        'message' => [
                            'actions' => $actionRequestParameters,
                        ],
                    ],
                ],
            ),
        );

        return $this->client->getResponse();
    }
}
