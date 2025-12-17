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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateDeliveryActionTest extends WebTestCase
{
    use DomainTestingTrait;
    use DocumentTestingTrait;

    private const PATCH_DELIVERY_BASE_URL = '/api/v1/deliveries/';
    private const VALID_CONFIGURATION = [
        'label' => 'test',
        'status' => true,
        'availabilityDate' => 12345,
        'expiryDate' => 12345,
        'metadata' => null,
    ];

    /** @var KernelBrowser */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $this->setUpTestDocumentManager();
    }

    public function testItReturnsSuccessResponse(): void
    {
        $delivery = $this->getTestDelivery();

        $this->doRequest($delivery->getId(), ['configuration' => self::VALID_CONFIGURATION]);

        $this->expectStatusCode(Response::HTTP_OK);
    }

    public function testItReturnsSuccessResponseWithDefaultStatus(): void
    {
        $delivery = $this->getTestDelivery();

        $this->doRequest(
            $delivery->getId(),
            [
                'configuration' => [
                    'label' => 'test',
                    'status' => null,
                    'availabilityDate' => 12345,
                    'expiryDate' => 12345,
                ],
            ],
        );

        $this->expectStatusCode(Response::HTTP_OK);
    }

    public function testItReturnsNotFoundIfDeliveryDoesNotExist(): void
    {
        $this->doRequest('invalid_delivery_id', ['configuration' => self::VALID_CONFIGURATION]);

        $this->expectStatusCode(Response::HTTP_NOT_FOUND);
    }

    /**
     * @dataProvider invalidPatchRequestContentProvider
     */
    public function testItReturnsValidationErrorIfRequestContentIsNotValid(array $content): void
    {
        $this->doRequest($this->getTestDelivery()->getId(), $content);

        $this->expectStatusCode(Response::HTTP_BAD_REQUEST);
    }

    public function invalidPatchRequestContentProvider(): array
    {
        return [
            [[]],
            [['configuration' => []]],
            [['configuration' => 1]],
            [['configuration' => 'string']],
            [['configuration' => null]],
            [['configuration' => true]],
        ];
    }

    private function expectStatusCode(int $status, ?string $responseClass = null): void
    {
        $response = $this->client->getResponse();

        $this->assertInstanceOf($responseClass ?? JsonResponse::class, $response);
        $this->assertEquals($status, $response->getStatusCode());
    }

    private function getPatchedDelivery(): array
    {
        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $content);

        return $content['data'];
    }

    private function doRequest(string $id, array $content = [], bool $withToken = true): void
    {
        $serverParams = [];

        $this->client->request(
            Request::METHOD_PATCH,
            self::PATCH_DELIVERY_BASE_URL . $id,
            [],
            [],
            $serverParams,
            json_encode($content),
        );
    }

    private function getTestDelivery(): Delivery
    {
        $delivery = $this->createTestDelivery();

        $this->saveDocument($delivery);

        return $delivery;
    }
}
