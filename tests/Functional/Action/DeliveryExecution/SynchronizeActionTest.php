<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Helper\Date;
use App\Qti\Compiler\QtiPackageCompiler;
use App\Service\DeliveryExecution\DeliveryExecutionFactory;
use App\Tests\Traits\AgsTestingTrait;
use App\Tests\Traits\CacheTestingTrait;
use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\OAuth2SecurityTestingTrait;
use App\Tests\Traits\RegistrationRepositoryTestingTrait;
use App\Traits\FilesystemTrait;
use DateTimeInterface;
use League\Flysystem\Filesystem;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\CacheInterface;

class SynchronizeActionTest extends WebTestCase
{
    use DocumentTestingTrait;
    use DomainTestingTrait;
    use OAuth2SecurityTestingTrait;
    use CacheTestingTrait;
    use FilesystemTrait;
    use AgsTestingTrait;
    use RegistrationRepositoryTestingTrait;

    private const BASE_URL = '/api/v1/delivery-executions/sync';

    private KernelBrowser $client;
    private Filesystem $qtiCompiledDeliveriesStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->cache = static::getContainer()->get(CacheInterface::class);
        $this->qtiCompiledDeliveriesStorage = static::getContainer()->get('qti_compiled_deliveries.storage');
        $this->qtiCompiledDeliveriesStorage->write(
            $this->buildPathFor('deliveryId', QtiPackageCompiler::COMPACT_TEST_FILE_NAME),
            file_get_contents(__DIR__ . '/../../../Resources/Qti/CompiledPackages/Basic/compact-test.xml'),
        );
        $this->mockRegistrationRepository();
        $this->mockPublishScore($this->never());
        $this->setUpTestDocumentManager();
        $this->saveDocument($this->createTestDelivery('deliveryId'));
    }

    public function testSuccessfullyCreatedInitialDeliveryExecution(): void
    {
        $content = [
            "deliveryExecutionId" => "mode#userId#deliveryId#attemptId#1",
            "ltiLaunchParameters" => [
                "result_id" => "mode#userId#deliveryId#attemptId#1",
            ],
            "status" => "initial",
            "startedAt" => "2023-07-11T16:29:24.016+02:00",
        ];

        $this->assertHasNoDocumentWithId(DeliveryExecution::class, $content['deliveryExecutionId']);

        $this->client->request(
            Request::METHOD_POST,
            self::BASE_URL,
            server: ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createAccessToken())],
            content: json_encode($content),
        );
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $deliveryExecution = $this->findDocumentById(
            DeliveryExecution::class,
            $content['deliveryExecutionId'],
        );
        $this->assertInstanceOf(DeliveryExecution::class, $deliveryExecution);
        $this->assertEquals(
            $content['status'],
            $deliveryExecution->getStatus(),
        );
        $this->assertNull(
            $deliveryExecution->getFinishedAt(),
        );
    }

    public function testSuccessfullyCreatedNewDeliveryExecutionWithOptionalLtiLaunchParameters(): void
    {
        $content = [
            "deliveryExecutionId" => "mode#userId#deliveryId#attemptId#1",
            "ltiLaunchParameters" => [
                "result_id" => "mode#userId#deliveryId#attemptId#1",
                "lti_version" => "LTI-1p3",
                "roles" => [],
            ],
            "extraStateData" => [
                'comments' => [
                    'itemId' => 'comment1',
                ],
            ],
            "status" => "initial",
            "startedAt" => "2023-07-11T16:29:24.016+02:00",
        ];

        $this->assertHasNoDocumentWithId(DeliveryExecution::class, $content['deliveryExecutionId']);

        $this->client->request(
            Request::METHOD_POST,
            self::BASE_URL,
            server: ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createAccessToken())],
            content: json_encode($content),
        );
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $document = $this->findDocumentById(DeliveryExecution::class, $content['deliveryExecutionId']);
        $this->assertInstanceOf(DeliveryExecution::class, $document);
        $this->assertEquals(
            $content['ltiLaunchParameters']['lti_version'],
            $document->getLtiLaunchParameters()['lti_version'],
        );
        $this->assertEquals(
            $content['extraStateData']['comments']['itemId'],
            $document->getExtraStateData()->getComments()['itemId'],
        );
        $this->assertEquals(
            $content['status'],
            $document->getStatus(),
        );
        $this->assertNull(
            $document->getFinishedAt(),
        );
    }

    public function testSuccessfullySynchronizeFinalisedNotFinishedDeliveryExecution(): void
    {
        $content = [
            "deliveryExecutionId" => "userId#deliveryId#attemptId#1",
            "ltiLaunchParameters" => [
                "result_id" => "userId#deliveryId#attemptId#1",
                "lti_version" => "LTI-1p3",
                "roles" => [],
            ],
            "extraStateData" => [
                "flaggedItems" => [],
                "comments" => [
                    'Q01' => 'comment1',
                ],
                "traceData" => [],
                "toolStates" => [],
                "itemStates" => [
                    "Q01" => "{\"RESPONSE\":{\"response\":{\"base\":{\"identifier\":\"chinese\"}},\"validity\":true}}",
                ],
                "temporaryItemStates" => [],
                "plagiarismReports" => [],
                "durationStorage" => [
                    "serverDurations" => [
                        [
                            "qtiComponentIdentifier" => "Q01",
                            "startedAt" => 1.689692241494425E9,
                            "endedAt" => 1.689692247100502E9,
                        ],
                        [
                            "qtiComponentIdentifier" => "Q02",
                            "startedAt" => 1.689692247119413E9,
                            "endedAt" => null,
                        ],
                    ],
                ],
                "externalTimerDefinition" => [
                    "externalTimerData" => null,
                ],
                "externalScoredItems" => [],
            ],
            "qtiSdkEncodedTestSession" => "DQEATQEBAAAAAAEEAFRQMDEAABAAAwAAAAAAAAAAAQAAAAABAAADAAABAQAAAQQAUFQ1UwkAY29tcGxldGVkAAIAAAAAAAAAAAAAAQAAAAAAAAAAAAEAAAAAAAABBwBjaGluZXNlAAABAAABAAAAAQAAAAABAQABAAABAQAAAQQAUFQwUwcAdW5rbm93bgAEAAAAAAABAAAAAAEAAAAAAAAAAAEAAAIAAAAAAQAAAAAAAAAAAQAAAwAAAAABAAAAAAAAAEABAQABAAAAAQEAAAAAAgAAAAEAAAAAAQIAAAAAAAEAAAAEAFBUMFMNAG5vdF9hdHRlbXB0ZWQAAgAAAAAABAAAAAABAAAAAAAAAAABAQACAAAAAQAAAAADAAMAVDAxAAEEAFBUNVMEAFRQMDEAAQQAUFQ1UwMAUzAxAAEEAFBUNVM=",
            "status" => "interacting",
            "startedAt" => "2023-07-11T16:29:24.016+02:00",
        ];

        $this->assertHasNoDocumentWithId(DeliveryExecution::class, $content['deliveryExecutionId']);

        $this->client->request(
            Request::METHOD_POST,
            self::BASE_URL,
            server: ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createAccessToken())],
            content: json_encode($content),
        );
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $document = $this->findDocumentById(DeliveryExecution::class, $content['deliveryExecutionId']);
        $this->assertInstanceOf(DeliveryExecution::class, $document);
        $this->assertEquals(
            $content['ltiLaunchParameters']['lti_version'],
            $document->getLtiLaunchParameters()['lti_version'],
        );
        $this->assertEquals(
            $content['extraStateData']['comments']['Q01'],
            $document->getExtraStateData()->getComments()['Q01'],
        );
        $this->assertTrue(
            $document->isStateFinal(),
        );
        $this->assertNotNull(
            $document->getFinishedAt(),
        );
    }

    public function testSuccessfullyOverrideDeliveryExecution(): void
    {
        $content = [
            "deliveryExecutionId" => "mode#userId#deliveryId#attemptId#1",
            "ltiLaunchParameters" => [
                "result_id" => "mode#userId#deliveryId#attemptId#1",
            ],
            "qtiSdkEncodedTestSession" => "DQEATQEBAAAAAAEEAFRQMDEAABAAAwAAAAAAAAAAAQAAAAABAAADAAABAQAAAQQAUFQ1UwkAY29tcGxldGVkAAIAAAAAAAAAAAAAAQAAAAAAAAAAAAEAAAAAAAABBwBjaGluZXNlAAABAAABAAAAAQAAAAABAQABAAABAQAAAQQAUFQwUwcAdW5rbm93bgAEAAAAAAABAAAAAAEAAAAAAAAAAAEAAAIAAAAAAQAAAAAAAAAAAQAAAwAAAAABAAAAAAAAAEABAQABAAAAAQEAAAAAAgAAAAEAAAAAAQIAAAAAAAEAAAAEAFBUMFMNAG5vdF9hdHRlbXB0ZWQAAgAAAAAABAAAAAABAAAAAAAAAAABAQACAAAAAQAAAAADAAMAVDAxAAEEAFBUNVMEAFRQMDEAAQQAUFQ1UwMAUzAxAAEEAFBUNVM=",
            "status" => "initial",
            "startedAt" => "2023-07-11T16:29:24.016+02:00",
        ];
        $deliveryExecution = DeliveryExecutionFactory::create(
            $content['deliveryExecutionId'],
            $content['ltiLaunchParameters'],
            null,
            status: $content['status'],
            startedAt: Date::createFromDefaultFormat($content['startedAt']),
        );
        $this->assertHasNoDocumentWithId(DeliveryExecution::class, $content['deliveryExecutionId']);
        $this->saveDocument($deliveryExecution);
        $this->assertHasDocumentWithId(DeliveryExecution::class, $content['deliveryExecutionId']);

        $overrideContent = array_merge($content, [
            "status" => "closed",
            "finishedAt" => "2023-07-11T16:29:24.016+02:00",
        ]);

        $this->client->request(
            Request::METHOD_POST,
            self::BASE_URL,
            server: ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createAccessToken())],
            content: json_encode($overrideContent),
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $document = $this->findDocumentById(DeliveryExecution::class, $overrideContent['deliveryExecutionId']);

        self::assertInstanceOf(DeliveryExecution::class, $document);
        self::assertEquals($overrideContent['ltiLaunchParameters'], $document->getLtiLaunchParameters());
        self::assertEquals($overrideContent['status'], $document->getStatus());
        self::assertEquals(
            $overrideContent['finishedAt'],
            $document->getFinishedAt()->format(DateTimeInterface::RFC3339_EXTENDED),
        );
    }

    public function testFailedWithConstraints(): void
    {
        $content = [
            "deliveryExecutionId" => "",
            "ltiLaunchParameters" => [
                "result_id" => "",
            ],
            "status" => "",
            "startedAt" => null,
        ];

        $this->client->request(
            Request::METHOD_POST,
            self::BASE_URL,
            server: ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createAccessToken())],
            content: json_encode($content),
        );
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals(
            "[deliveryExecutionId]: This value should not be blank., [ltiLaunchParameters][result_id]: This value should not be blank., [status]: The value you selected is not a valid choice., [startedAt]: This value should not be blank.",
            json_decode($response->getContent(), true)['error']['message'],
        );
    }

    public function testFailedWithFinishedValidation(): void
    {
        $content = [
            "deliveryExecutionId" => "mode#userId#deliveryId#attemptId#1",
            "ltiLaunchParameters" => [
                "result_id" => "mode#userId#deliveryId#attemptId#1",
            ],
            "status" => "closed",
            "startedAt" => "2023-07-11T16:29:24.016+02:00",
        ];

        $this->assertHasNoDocumentWithId(DeliveryExecution::class, $content['deliveryExecutionId']);
        $this->client->request(
            Request::METHOD_POST,
            self::BASE_URL,
            server: ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $this->createAccessToken())],
            content: json_encode($content),
        );
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals(
            "[mode#userId#deliveryId#attemptId#1] DeliveryExecution is final but has no end date",
            json_decode($response->getContent(), true)['error']['message'],
        );
        $this->assertHasNoDocumentWithId(DeliveryExecution::class, $content['deliveryExecutionId']);
    }

    private function createAccessToken(): string
    {
        return $this->createOAuth2AccessToken(scopes: ['tao-offline']);
    }
}
