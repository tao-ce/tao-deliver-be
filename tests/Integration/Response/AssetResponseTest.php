<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Response;

use App\Response\AssetResponse;
use App\Traits\FilesystemTrait;
use Carbon\Carbon;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AssetResponseTest extends KernelTestCase
{
    use FilesystemTrait;

    /** @var FilesystemOperator */
    private $assetManager;

    /** @var string */
    private $assetPath;

    /** @var AssetResponse */
    private $subject;

    public function setUp(): void
    {
        static::bootKernel();

        $this->assetManager = static::getContainer()->get('qti_asset_manager.storage');

        $this->assetPath = $this->buildPathFor('someFilepath', 'planeStrategy.png');
        $this->createAssetFile($this->assetPath);

        $resource = $this->assetManager->readStream($this->assetPath);
        $assetMimeType = $this->assetManager->mimeType($this->assetPath);
        $assetSize = $this->assetManager->fileSize($this->assetPath);
        $assetTimestamp = $this->assetManager->lastModified($this->assetPath);

        $this->subject = new AssetResponse($resource, $assetMimeType, $assetTimestamp, $assetSize);
    }

    public function testCreateAssetResponse(): void
    {
        $this->assertEquals(Response::HTTP_OK, $this->subject->getStatusCode());
        $this->assertEquals(false, $this->subject->getContent());
    }

    public function testCreateAssetResponseWithPartialContent(): void
    {
        $request = Request::create('/uri', Request::METHOD_GET, [], [], [], ['HTTP_RANGE' => 'bytes=0-10']);

        $this->subject->prepare($request);

        $this->assertEquals(Response::HTTP_PARTIAL_CONTENT, $this->subject->getStatusCode());
        $this->assertEquals(false, $this->subject->getContent());
    }

    public function testCreateAssetResponseWithPartialContentWithNoStartRange(): void
    {
        $request = Request::create('/uri', Request::METHOD_GET, [], [], [], ['HTTP_RANGE' => 'bytes=-10']);

        $this->subject->prepare($request);

        $this->assertEquals(Response::HTTP_PARTIAL_CONTENT, $this->subject->getStatusCode());
        $this->assertEquals(false, $this->subject->getContent());
    }

    public function testCreateAssetResponseWithNoRangeSupported(): void
    {
        $request = Request::create('/uri', Request::METHOD_GET, [], [], [], ['HTTP_RANGE' => 'bytes=-1000000000']);

        $this->subject->prepare($request);

        $this->assertEquals(Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE, $this->subject->getStatusCode());
        $this->assertEquals(false, $this->subject->getContent());
    }

    public function testCreateAssetResponseWithRangeIfStatement(): void
    {
        $request = Request::create(
            '/uri',
            Request::METHOD_GET,
            [],
            [],
            [],
            [
                'HTTP_RANGE' => 'bytes=0-10',
                'HTTP_IF-RANGE' => Carbon::now()->format('D, d M Y H:i:s'),
            ],
        );

        $this->subject->prepare($request);

        $this->assertEquals(Response::HTTP_OK, $this->subject->getStatusCode());
        $this->assertEquals(false, $this->subject->getContent());
    }

    public function testCreateAssetResponseWithBadRangeIfStatement(): void
    {
        $request = Request::create(
            '/uri',
            Request::METHOD_GET,
            [],
            [],
            [],
            [
                'HTTP_RANGE' => 'bytes=0-10',
                'HTTP_IF-BROKEN-RANGE' => 'some wrong date GMT',
            ],
        );

        $this->subject->prepare($request);

        $this->assertEquals(Response::HTTP_PARTIAL_CONTENT, $this->subject->getStatusCode());
        $this->assertEquals(false, $this->subject->getContent());
    }

    public function testCreateAssetResponseWithFalseResourceSize(): void
    {

        $this->assetPath = $this->buildPathFor('someFilepath', 'planeStrategy.png');
        $this->createAssetFile($this->assetPath);

        $resource = $this->assetManager->readStream($this->assetPath);
        $assetMimeType = $this->assetManager->mimeType($this->assetPath);
        $assetTimestamp = $this->assetManager->lastModified($this->assetPath);

        $subject = new AssetResponse($resource, $assetMimeType, $assetTimestamp, null);

        $request = Request::create(
            '/uri',
            Request::METHOD_GET,
            [],
            [],
            [],
            [
                'HTTP_RANGE' => 'bytes=0-10',
                'HTTP_IF-RANGE' => 'some wrong date GMT',
            ],
        );

        $response = $subject->prepare($request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals(false, $response->getContent());
    }

    private function createAssetFile(string $path): void
    {
        $this->assetManager->write(
            $path,
            file_get_contents('tests/Resources/Asset/planeStrategy.png'),
        );
    }
}
