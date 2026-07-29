<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\Attachment;

use App\ContentServiceApi\Gateway\ContentServiceApiGateway;
use App\TestItemAttachment\Service\AttachmentRegistry;
use App\TestItemAttachment\Service\ItemCategoryBasedAttachmentRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class ItemCategoryBasedAttachmentRegistryTest extends TestCase
{
    private const TENANT_ID = 'test';
    private const KEY_NAME = 'key';

    private ItemCategoryBasedAttachmentRegistry $sut;
    private ContentServiceApiGateway $contentServiceApiGateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contentServiceApiGateway = $this->createMock(ContentServiceApiGateway::class);

        $this->sut = new ItemCategoryBasedAttachmentRegistry(
            new AttachmentRegistry(
                $this->createMock(LoggerInterface::class),
                $this->contentServiceApiGateway,
                new ArrayAdapter(),
                self::KEY_NAME,
                10,
                5,
            ),
        );
    }

    /**
     * @dataProvider dataProvider
     */
    public function testResolveAttachments(array $expected, array $attachments, bool $cached = true): void
    {
        $categories = array_keys($expected);
        $this->contentServiceApiGateway
            ->expects($cached ? $this->once() : $this->exactly(2))
            ->method('getUploadedFiles')
            ->with(self::TENANT_ID, array_map(fn(string $category): string => substr($category, 17), $categories))
            ->willReturn($attachments);
        for ($i = 0; $i < 2; $i++) {
            $this->assertEquals(
                $expected,
                $this->sut->resolveAttachments(self::TENANT_ID, $categories),
            );
        }
    }

    public function testNoDataCachedUponTransportException(): void
    {
        $n = 2;
        $this->contentServiceApiGateway
            ->expects($this->exactly($n))
            ->method('getUploadedFiles')
            ->willThrowException(new TransportException());
        for ($i = 0; $i < $n; $i++) {
            $this->assertEmpty(
                $this->sut->resolveAttachments(
                    self::TENANT_ID,
                    ['x-tao-attachment-2cabf7ac-2017-4261-993c-702225020d12'],
                ),
            );
        }
    }

    public function testInternalErrorThrown(): void
    {
        $exception = $this->createMock(ServerExceptionInterface::class);
        $this->expectExceptionObject($exception);
        $this->contentServiceApiGateway
            ->method('getUploadedFiles')
            ->willThrowException($exception);
        $this->sut->resolveAttachments(
            self::TENANT_ID,
            ['x-tao-attachment-2cabf7ac-2017-4261-993c-702225020d12'],
        );
    }

    public static function dataProvider(): array
    {
        return [
            'Matching signing keys' => [
                'expected' => [
                    'x-tao-attachment-2cabf7ac-2017-4261-993c-702225020d12' => [
                        'id' => '2cabf7ac-2017-4261-993c-702225020d12',
                        'name' => 'attachment-1.pdf',
                        'type' => 'application/pdf',
                        'url' => 'https://taotesting.com/cdn/application-1.pdf?KeyName=' . self::KEY_NAME,
                    ],
                    'x-tao-attachment-efacc32c-73d2-429a-9e31-fe7b46f4d46e' => [
                        'id' => 'efacc32c-73d2-429a-9e31-fe7b46f4d46e',
                        'name' => 'attachment-2.pdf',
                        'type' => 'application/pdf',
                        'url' => 'https://taotesting.com/cdn/application-2.pdf?KeyName=' . self::KEY_NAME,
                    ],
                ],
                'attachments' => [
                    [
                        'asset' => [
                            'id' => '2cabf7ac-2017-4261-993c-702225020d12',
                            'type' => 'application/pdf',
                            'tenantId' => self::TENANT_ID,
                            'userId' => 'admin',
                            'creationTimestamp' => '2025-08-25T14:23:54.719Z',
                            'virtualPath' => self::TENANT_ID . '/attachment-1.pdf',
                        ],
                        'publicUrl' => 'https://taotesting.com/cdn/application-1.pdf?KeyName=' . self::KEY_NAME,
                    ],
                    [
                        'asset' => [
                            'id' => 'efacc32c-73d2-429a-9e31-fe7b46f4d46e',
                            'type' => 'application/pdf',
                            'tenantId' => self::TENANT_ID,
                            'userId' => 'admin',
                            'creationTimestamp' => '2025-08-25T14:23:54.719Z',
                            'virtualPath' => self::TENANT_ID . '/attachment-2.pdf',
                        ],
                        'publicUrl' => 'https://taotesting.com/cdn/application-2.pdf?KeyName=' . self::KEY_NAME,
                    ],
                ],
            ],
            'Mismatching signing keys' => [
                'expected' => [
                    'x-tao-attachment-2cabf7ac-2017-4261-993c-702225020d12' => [
                        'id' => '2cabf7ac-2017-4261-993c-702225020d12',
                        'name' => 'attachment-1.pdf',
                        'type' => 'application/pdf',
                        'url' => 'https://taotesting.com/cdn/application-1.pdf?KeyName=unknown',
                    ],
                    'x-tao-attachment-efacc32c-73d2-429a-9e31-fe7b46f4d46e' => [
                        'id' => 'efacc32c-73d2-429a-9e31-fe7b46f4d46e',
                        'name' => 'attachment-2.pdf',
                        'type' => 'application/pdf',
                        'url' => 'https://taotesting.com/cdn/application-2.pdf?KeyName=unknown',
                    ],
                ],
                'attachments' => [
                    [
                        'asset' => [
                            'id' => '2cabf7ac-2017-4261-993c-702225020d12',
                            'type' => 'application/pdf',
                            'tenantId' => self::TENANT_ID,
                            'userId' => 'admin',
                            'creationTimestamp' => '2025-08-25T14:23:54.719Z',
                            'virtualPath' => self::TENANT_ID . '/attachment-1.pdf',
                        ],
                        'publicUrl' => 'https://taotesting.com/cdn/application-1.pdf?KeyName=unknown',
                    ],
                    [
                        'asset' => [
                            'id' => 'efacc32c-73d2-429a-9e31-fe7b46f4d46e',
                            'type' => 'application/pdf',
                            'tenantId' => self::TENANT_ID,
                            'userId' => 'admin',
                            'creationTimestamp' => '2025-08-25T14:23:54.719Z',
                            'virtualPath' => self::TENANT_ID . '/attachment-2.pdf',
                        ],
                        'publicUrl' => 'https://taotesting.com/cdn/application-2.pdf?KeyName=unknown',
                    ],
                ],
                'cached' => false,
            ],
        ];
    }
}
