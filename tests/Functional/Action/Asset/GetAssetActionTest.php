<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\Asset;

use App\Generator\Asset\CloudCdnSignedUrlGenerator;
use App\Registry\SignedUrlGeneratorRegistry;
use App\Response\AssetResponse;
use App\Tests\Traits\LoggerTestingTrait;
use App\Traits\FilesystemTrait;
use League\Flysystem\FilesystemWriter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetAssetActionTest extends WebTestCase
{
    use LoggerTestingTrait;
    use FilesystemTrait;

    /** @var FilesystemWriter */
    private $qtiAssetManagerStorage;

    /** @var SignedUrlGeneratorRegistry */
    private $signedUrlGeneratorRegistry;

    /** @var KernelBrowser */
    private $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->setUpTestLogHandler();

        $this->qtiAssetManagerStorage = static::getContainer()->get('qti_asset_manager.storage');
        $this->signedUrlGeneratorRegistry = static::getContainer()->get(SignedUrlGeneratorRegistry::class);
    }

    public function testItReturnsAsset(): void
    {
        $assetPath = $this->buildPathFor('f70340df-c197-48e1-82aa-ffffb821fb57', 'planeStrategy.png');
        $this->createAssetFile($assetPath);

        $signedUrl = $this->signedUrlGeneratorRegistry->getGenerator(CloudCdnSignedUrlGenerator::NAME)->generateDownloadUrl($assetPath);

        $this->client->request(
            Request::METHOD_GET,
            $signedUrl,
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testItReturnsPartialAsset(): void
    {
        $assetPath = $this->buildPathFor('f70340df-c197-48e1-82aa-ffffb821fb57', 'planeStrategy.png');
        $this->createAssetFile($assetPath);
        $responseRange = 'bytes=0-100';
        $signedUrl = $this->signedUrlGeneratorRegistry->getGenerator(CloudCdnSignedUrlGenerator::NAME)->generateDownloadUrl($assetPath);

        ob_start();
        $this->client->request(
            Request::METHOD_GET,
            $signedUrl,
            [],
            [],
            ['HTTP_RANGE' => $responseRange],
        );
        // Do normal request stuff here
        $response = $this->client->getResponse();
        if (get_class($response) == AssetResponse::class) {
            $response = new Response(
                ob_get_contents(),
                $response->getStatusCode(),
                $response->headers->all(),
            );
        }
        ob_end_clean();

        $this->assertEquals(Response::HTTP_PARTIAL_CONTENT, $response->getStatusCode());
        $this->assertStringContainsString('bytes 0-100/13360', $response->headers->get('Content-Range'));
    }

    public function testItReturnsNotFoundIfResourceDoesNotExist(): void
    {
        $assetPath = $this->buildPathFor('someNonExitingFolder', 'doesNotExit.png');

        $signedUrl = $this->signedUrlGeneratorRegistry->getGenerator(CloudCdnSignedUrlGenerator::NAME)->generateDownloadUrl($assetPath);

        $this->client->request(
            Request::METHOD_GET,
            $signedUrl,
            [],
            [],
            [],
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testItReturnsUnprocessableEntity(): void
    {
        $assetPath = $this->buildPathFor('someNonExitingFolder', 'doesNotExit.png');

        $signedUrl = $this->signedUrlGeneratorRegistry->getGenerator(CloudCdnSignedUrlGenerator::NAME)->generateDownloadUrl($assetPath);

        $this->client->request(
            Request::METHOD_GET,
            $signedUrl . 'someExtraString',
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    private function createAssetFile(string $path): void
    {
        $this->qtiAssetManagerStorage->write(
            $path,
            file_get_contents(__DIR__ . '/../../../Resources/Asset/planeStrategy.png'),
        );
    }
}
