<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Functional\Action\Attachments;

use App\Tests\Traits\DocumentTestingTrait;
use App\Tests\Traits\DomainTestingTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UploadFileActionTest extends WebTestCase
{
    use DocumentTestingTrait;
    use DomainTestingTrait;

    /** @var KernelBrowser */
    private $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $this->setUpTestDocumentManager();
    }

    public function testItUploadFileSuccessful(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution();

        $this->saveDocument($deliveryExecution);

        $file = new UploadedFile(
            'tests/Resources/Asset/planeStrategy.png',
            'planeStrategy.png',
            'image/png',
            null,
        );

        $this->doRequest(
            $file,
        );

        $response = $this->client->getResponse();

        $content = json_decode($response->getContent(), true);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($content['success']);
    }

    private function doRequest(UploadedFile $file): void
    {
        $this->client->request(
            Request::METHOD_PUT,
            sprintf('/api/v1/attachments/%s', urlencode('/test/path/sub_path/filename')),
            [],
            [],
            [],
            $file->getContent(),
        );
    }
}
