<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\Publication;

use App\Domain\Publication\Model\Publication;
use App\Messenger\Message\PublicationMessage;
use App\Service\Publication\CreatePublicationService;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use App\Tests\Traits\OAuth2SecurityTestingTrait;
use App\Traits\FilesystemTrait;
use Carbon\Carbon;
use League\Flysystem\FilesystemReader;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreatePublicationActionTest extends WebTestCase
{
    use MessengerTestingTrait;
    use LoggerTestingTrait;
    use FilesystemTrait;
    use DocumentTestingTrait;
    use OAuth2SecurityTestingTrait;

    private const POST_PUBLICATION_BASE_URL = '/api/v1/publications';

    /** @var KernelBrowser */
    private $client;

    /** @var string */
    private $base64Content;

    /** @var array */
    private $validConfiguration;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $this->setUpTestLogHandler();
        $this->setUpTestMessageBus();
        $this->setUpTestDocumentManager();

        $availabilityDate = Carbon::now()->getTimestamp();
        $expiryDate = Carbon::now()->getTimestamp();

        $this->base64Content = file_get_contents(__DIR__ . '/../../../Resources/Qti/Base64EncodedPackages/basic_package.txt');
        $this->validConfiguration = [
            'label' => 'test',
            'status' => true,
            'availabilityDate' => $availabilityDate,
            'expiryDate' => $expiryDate,
            'metadata' => null,
        ];
    }

    public function testItCreatesAPublication(): void
    {
        $tenantId = 'dev-acc.dev-ins';
        $availabilityDate = Carbon::now()->getTimestamp();
        $expiryDate = Carbon::now()->getTimestamp();

        $configuration = [
            'label' => 'test',
            'status' => true,
            'availabilityDate' => $availabilityDate,
            'expiryDate' => $expiryDate,
            'metadata' => null,
        ];
        $parameters = [
            'package' => $this->base64Content,
            'configuration' => $configuration,
        ];

        /** @var FilesystemReader $storage */
        $storage = static::getContainer()->get('base64_zip.storage');

        $this->client->request(
            Request::METHOD_POST,
            self::POST_PUBLICATION_BASE_URL,
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessToken($tenantId))],
            json_encode($parameters),
        );

        $response = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_ACCEPTED, $response->getStatusCode());

        $this->assertHasDocumentWithId(Publication::class, $responseData['data']['id']);

        /** @var Publication $publicationStored */
        $publicationStored = $this->findDocumentById(Publication::class, $responseData['data']['id']);

        $this->assertEquals(Publication::STATUS_CREATED, $publicationStored->getStatus());
        $this->assertEquals($tenantId, $publicationStored->getTenantId());
        $this->assertEquals($configuration, $publicationStored->getPackageConfiguration());

        $expectedPath = $this->buildPathFor($publicationStored->getId(), CreatePublicationService::BASE_64_FILE_NAME);

        $this->assertTrue($storage->has($expectedPath));
        $this->assertEquals($this->base64Content, $storage->read($expectedPath));

        $this->assertCountTransportMessages('publication', 1);

        /** @var PublicationMessage $messagePushedToBroker */
        $messagePushedToBroker = current($this->getTransportMessages('publication'))->getMessage();

        $this->assertHasTransportMessage('publication', PublicationMessage::class);

        $expectedConfiguration = [
            'status' => true,
            'availabilityDate' => $availabilityDate,
            'expiryDate' => $expiryDate,
            'metadata' => null,
        ];

        $this->assertEquals($tenantId, $messagePushedToBroker->getTenantId());
        $this->assertEquals($expectedConfiguration, $messagePushedToBroker->getConfiguration());
        $this->assertEquals($expectedPath, $messagePushedToBroker->getBase64ZipPath());

        $this->assertHasLogRecordWithMessage(
            sprintf('[%s] - Publication was created with success', $publicationStored->getId()),
            Logger::INFO,
            'audit_platform',
        );

        $this->assertEquals(
            $publicationStored,
            current($this->getLogRecords('audit_platform'))['context']['publication'],
        );
    }
    public function testItCreatesAPublicationWithDefaultStatus(): void
    {
        $configuration =  $this->validConfiguration;
        $configuration['status'] = null;

        $parameters = [
            'package' => $this->base64Content,
            'configuration' => $configuration,
        ];

        $this->client->request(
            Request::METHOD_POST,
            self::POST_PUBLICATION_BASE_URL,
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessToken())],
            json_encode($parameters),
        );

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_ACCEPTED, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);

        /** @var Publication $publicationStored */
        $publicationStored = $this->findDocumentById(Publication::class, $responseData['data']['id']);

        $this->assertEquals(Publication::STATUS_CREATED, $publicationStored->getStatus());

        $this->assertEquals($this->validConfiguration, $publicationStored->getPackageConfiguration());

        $this->assertCountTransportMessages('publication', 1);

        /** @var PublicationMessage $messagePushedToBroker */
        $messagePushedToBroker = current($this->getTransportMessages('publication'))->getMessage();

        $this->assertHasTransportMessage('publication', PublicationMessage::class);
        $this->assertEquals($publicationStored->getId(), $messagePushedToBroker->getPublicationId());


        $this->assertHasLogRecordWithMessage(
            sprintf('[%s] - Publication was created with success', $publicationStored->getId()),
            Logger::INFO,
            'audit_platform',
        );
    }

    public function testItFailsIfMissingConfiguration(): void
    {
        $rawContent = json_encode([
            'package' => 'base64',
        ]);

        $this->client->request(
            Request::METHOD_POST,
            self::POST_PUBLICATION_BASE_URL,
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessToken())],
            $rawContent,
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testItFailsIfMissingPackageAndConfiguration(): void
    {
        $rawContent = json_encode([]);

        $this->client->request(
            Request::METHOD_POST,
            self::POST_PUBLICATION_BASE_URL,
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessToken())],
            $rawContent,
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testItFailsIfMissingPackage(): void
    {
        $rawContent = json_encode([
            'configuration' => ['configuration'],
        ]);

        $this->client->request(
            Request::METHOD_POST,
            self::POST_PUBLICATION_BASE_URL,
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessToken())],
            $rawContent,
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testItFailsIfPackageIsEmptyString(): void
    {
        $rawContent = json_encode([
            'package' => '',
            'configuration' => ['configuration'],
        ]);

        $this->client->request(
            Request::METHOD_POST,
            self::POST_PUBLICATION_BASE_URL,
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessToken())],
            $rawContent,
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testItFailsIfPackageIsNull(): void
    {
        $rawContent = json_encode([
            'package' => null,
            'configuration' => ['configuration'],
        ]);

        $this->client->request(
            Request::METHOD_POST,
            self::POST_PUBLICATION_BASE_URL,
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessToken())],
            $rawContent,
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testItFailsIfPackageHasWrongType(): void
    {
        $rawContent = json_encode([
            'package' => ['array'],
            'configuration' => ['configuration'],
        ]);

        $this->client->request(
            Request::METHOD_POST,
            self::POST_PUBLICATION_BASE_URL,
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessToken())],
            $rawContent,
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testItFailsIfConfigurationIsEmpty(): void
    {
        $rawContent = json_encode([
            'package' => 'package',
            'tenantId' => 'tenantId',
            'configuration' => [],
        ]);

        $this->client->request(
            Request::METHOD_POST,
            self::POST_PUBLICATION_BASE_URL,
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessToken())],
            $rawContent,
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testItFailsIfConfigurationIsNull(): void
    {
        $rawContent = json_encode([
            'package' => 'package',
            'configuration' => null,
        ]);

        $this->client->request(
            Request::METHOD_POST,
            self::POST_PUBLICATION_BASE_URL,
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessToken())],
            $rawContent,
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testItFailsIfConfigurationHasWrongType(): void
    {
        $rawContent = json_encode([
            'package' => 'package',
            'configuration' => 'string',
        ]);

        $this->client->request(
            Request::METHOD_POST,
            self::POST_PUBLICATION_BASE_URL,
            [],
            [],
            ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createOAuth2AccessToken())],
            $rawContent,
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }
}
