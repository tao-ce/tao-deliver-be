<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\Asset;

use App\Generator\Asset\CloudCdnSignedUrlGenerator;
use App\Registry\SignedUrlGeneratorRegistry;
use App\Tests\Traits\LoggerTestingTrait;
use App\Traits\FilesystemTrait;
use League\Flysystem\FilesystemWriter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DownloadAssetActionTest extends WebTestCase
{
    use LoggerTestingTrait;
    use FilesystemTrait;

    /** @var FilesystemWriter */
    private $qtiAssetManagerStorage;

    /** @var SignedUrlGeneratorRegistry */
    private $signedUrlGeneratorRegistry;

    /** @var string */
    private $secretKey;

    /** @var UrlGeneratorInterface */
    private $urlGeneratorInterface;

    /** @var KernelBrowser */
    private $client;

    public function setUp(): void
    {
        parent::setUp();
        static::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->setUpTestLogHandler();

        $this->qtiAssetManagerStorage = static::getContainer()->get('qti_asset_manager.storage');
        $this->urlGeneratorInterface = static::getContainer()->get(UrlGeneratorInterface::class);
        $this->signedUrlGeneratorRegistry = static::getContainer()->get(SignedUrlGeneratorRegistry::class);
    }

    public function testItDownloadsAsset(): void
    {
        $assetPath = $this->buildPathFor('f70340df-c197-48e1-82aa-ffffb821fb57', 'planeStrategy.png');
        $this->createAssetFile($assetPath);

        $signedUrl = $this->signedUrlGeneratorRegistry->getGenerator(CloudCdnSignedUrlGenerator::NAME)->generateDownloadUrl(
            $assetPath,
            $this->urlGeneratorInterface->generate('api_v1_download_asset'),
        );

        $this->client->request(
            Request::METHOD_GET,
            $signedUrl,
        );

        $response = $this->client->getResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Content-Disposition'));
        $this->assertSame(
            'attachment; filename="f70340df-c197-48e1-82aa-ffffb821fb57/planeStrategy.png"',
            $response->headers->get('Content-Disposition'),
        );
    }

    private function createAssetFile(string $path): void
    {
        $this->qtiAssetManagerStorage->write(
            $path,
            file_get_contents(__DIR__ . '/../../../Resources/Asset/planeStrategy.png'),
        );
    }
}
