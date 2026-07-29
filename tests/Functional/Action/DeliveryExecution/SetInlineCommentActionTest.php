<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\Lti\LtiTokenResolverInterface;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\OAuth2SecurityTestingTrait;
use League\Flysystem\FilesystemWriter;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SetInlineCommentActionTest extends WebTestCase
{
    use DomainTestingTrait;
    use DocumentTestingTrait;
    use OAuth2SecurityTestingTrait;

    private const BASE_URL = '/api/v1/delivery-executions/%s/scoring/inline-comment';

    private KernelBrowser $client;
    private FilesystemWriter $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $this->setUpTestDocumentManager();

        $this->storage = static::getContainer()->get('delivery_execution_result.storage');
    }

    public function testAddNewComment(): void
    {
        $deliveryExecution = $this->getDeliveryExecution();

        $this->saveDocument($deliveryExecution);

        $response = $this->doRequest($deliveryExecution->getId(), 'test');

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $this->assertTrue($response['success']);
    }

    public function testRemoveCommentBySendEmpty(): void
    {
        $deliveryExecution = $this->getDeliveryExecution();

        $this->saveDocument($deliveryExecution);

        $this->doRequest($deliveryExecution->getId(), '');

        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    public function testItemIdIsRequired(): void
    {
        $deliveryExecution = $this->getDeliveryExecution();

        $this->saveDocument($deliveryExecution);

        $response = $this->doRequest($deliveryExecution->getId(), 'test', '');

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        $this->assertEquals('[itemId]: This value should not be blank.', $response['error']['message']);
    }

    public function testNotAllowedLearnedMode(): void
    {
        $deliveryExecution = $this->getDeliveryExecution();

        $this->saveDocument($deliveryExecution);

        $response = $this->doRequest(
            $deliveryExecution->getId(),
            'test',
            role: LtiTokenResolverInterface::LTI_ROLE_LEARNER,
        );

        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        $this->assertEquals(
            "[review#{$deliveryExecution->getId()}] invalid role",
            $response['error']['message'],
        );
    }

    private function doRequest(
        string $deliveryExecutionId,
        string $comment,
        string $itemId = 'itemId',
        string $role = LtiTokenResolverInterface::LTI_ROLE_INSTRUCTOR,
    ): array {
        $this->client->request(
            method: Request::METHOD_PUT,
            uri: sprintf(self::BASE_URL, rawurlencode("review#$deliveryExecutionId")),
            server: [
                'HTTP_AUTHORIZATION' => sprintf(
                    'Bearer %s',
                    $this->createOAuth2AccessToken(
                        $deliveryExecutionId,
                        [
                            LtiMessagePayloadInterface::CLAIM_LTI_ROLES => [$role],
                        ],
                    ),
                ),
            ],
            content: json_encode([
                'comment' => [
                    'comment' => $comment,
                ],
                'itemId' => $itemId,
            ]),
        );

        /** @var JsonResponse $response */
        $response = $this->client->getResponse();
        return json_decode($response->getContent(), true);
    }

    private function getDeliveryExecution(bool $isFinal = false): DeliveryExecution
    {
        return $this->createTestDeliveryExecution(
            status: $isFinal ? DeliveryExecution::STATUS_CLOSED : DeliveryExecution::STATUS_INITIAL,
        );
    }
}
