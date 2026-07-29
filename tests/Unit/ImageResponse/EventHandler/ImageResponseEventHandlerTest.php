<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\ImageResponse\EventHandler;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\ImageResponse\EventHandler\ImageResponseEventHandler;
use App\ImageResponse\Input\ImageResponse;
use App\ImageResponse\Input\Metadata;
use App\ImageResponse\Service\ImageResponseWriterService;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use App\Tests\Traits\DomainTestingTrait;
use DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Lock\LockFactory;

class ImageResponseEventHandlerTest extends KernelTestCase
{
    use DomainTestingTrait;

    private DeliveryExecutionService&MockObject $deliveryExecutionService;
    private ImageResponseEventHandler $sut;

    public static function invalidMessagesProvider(): array
    {
        return [
            'Bad status' => [
                new ImageResponse(
                    'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
                    '1',
                    new DateTime(),
                    'failed',
                    new Metadata(
                        '18e8c261-c478-4a7e-be99-55468f89aef5test-taker-1',
                        '76f244a88322',
                        '18e8c261-c478-4a7e-be99-55468f89aef5',
                        '18e8c261-c478-4a7e-be99-55468f89aef5',
                        'item-1',
                        'RESPONSE',
                        1,
                    ),
                ),
            ],
            'Missing page number' => [
                new ImageResponse(
                    'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
                    '1',
                    new DateTime(),
                    'success',
                    new Metadata(
                        '18e8c261-c478-4a7e-be99-55468f89aef5test-taker-1',
                        '76f244a88322',
                        '18e8c261-c478-4a7e-be99-55468f89aef5',
                        '18e8c261-c478-4a7e-be99-55468f89aef5',
                        'item-1',
                        'RESPONSE',
                    ),
                ),
            ],
            'Missing response ID' => [
                new ImageResponse(
                    'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
                    '1',
                    new DateTime(),
                    'success',
                    new Metadata(
                        '18e8c261-c478-4a7e-be99-55468f89aef5test-taker-1',
                        '76f244a88322',
                        '18e8c261-c478-4a7e-be99-55468f89aef5',
                        '18e8c261-c478-4a7e-be99-55468f89aef5',
                        'item-1',
                        pageNumber: 1,
                    ),
                ),
            ],
            'Missing item ID' => [
                new ImageResponse(
                    'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
                    '1',
                    new DateTime(),
                    'success',
                    new Metadata(
                        '18e8c261-c478-4a7e-be99-55468f89aef5test-taker-1',
                        '76f244a88322',
                        '18e8c261-c478-4a7e-be99-55468f89aef5',
                        '18e8c261-c478-4a7e-be99-55468f89aef5',
                        responseId: 'RESPONSE',
                        pageNumber: 1,
                    ),
                ),
            ],
            'Missing attempt ID' => [
                new ImageResponse(
                    'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
                    '1',
                    new DateTime(),
                    'success',
                    new Metadata(
                        '18e8c261-c478-4a7e-be99-55468f89aef5test-taker-1',
                        '76f244a88322',
                        '18e8c261-c478-4a7e-be99-55468f89aef5',
                        itemId: 'item-1',
                        responseId: 'RESPONSE',
                        pageNumber: 1,
                    ),
                ),
            ],
            'Missing session ID' => [
                new ImageResponse(
                    'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
                    '1',
                    new DateTime(),
                    'success',
                    new Metadata(
                        '18e8c261-c478-4a7e-be99-55468f89aef5test-taker-1',
                        '76f244a88322',
                        attemptId: '18e8c261-c478-4a7e-be99-55468f89aef5',
                        itemId: 'item-1',
                        responseId: 'RESPONSE',
                        pageNumber: 1,
                    ),
                ),
            ],
            'Missing delivery ID' => [
                new ImageResponse(
                    'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
                    '1',
                    new DateTime(),
                    'success',
                    new Metadata(
                        '18e8c261-c478-4a7e-be99-55468f89aef5test-taker-1',
                        sessionId: '18e8c261-c478-4a7e-be99-55468f89aef5',
                        attemptId: '18e8c261-c478-4a7e-be99-55468f89aef5',
                        itemId: 'item-1',
                        responseId: 'RESPONSE',
                        pageNumber: 1,
                    ),
                ),
            ],
            'Missing user session ID' => [
                new ImageResponse(
                    'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
                    '1',
                    new DateTime(),
                    'success',
                    new Metadata(
                        deliveryId: '76f244a88322',
                        sessionId: '18e8c261-c478-4a7e-be99-55468f89aef5',
                        attemptId: '18e8c261-c478-4a7e-be99-55468f89aef5',
                        itemId: 'item-1',
                        responseId: 'RESPONSE',
                        pageNumber: 1,
                    ),
                ),
            ],
        ];
    }

    /**
     * @before
     */
    public function init(): void
    {
        static::bootKernel();
        static::getContainer()->get('serializer');

        $this->deliveryExecutionService = $this
            ->getMockBuilder(DeliveryExecutionService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findDeliveryExecution', 'saveDeliveryExecution'])
            ->getMock();
        $this->sut = new ImageResponseEventHandler(
            static::getContainer()->get(LoggerInterface::class),
            static::getContainer()->get(ImageResponseWriterService::class),
            $this->deliveryExecutionService,
            static::getContainer()->get(LockFactory::class),
        );
    }

    /**
     * @dataProvider invalidMessagesProvider
     */
    public function testItSkipsInvalidMessage(ImageResponse $message): void
    {
        $this->deliveryExecutionService
            ->expects($this->never())
            ->method('findDeliveryExecution');
        $this->deliveryExecutionService
            ->expects($this->never())
            ->method('saveDeliveryExecution');

        ($this->sut)($message);
    }

    public function testItSkipsWhenDeliveryExecutionNotFound(): void
    {
        $deliveryId = '76f244a88322';
        $sessionId = $attemptId = '18e8c261-c478-4a7e-be99-55468f89aef5';
        $tenantId = '1';
        $userId = 'test-taker-1';
        $message = new ImageResponse(
            'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
            $tenantId,
            new DateTime(),
            'success',
            new Metadata(
                "$sessionId$userId",
                $deliveryId,
                $sessionId,
                $attemptId,
                'item-1',
                'RESPONSE',
                1,
            ),
        );
        $id = implode(
            '#',
            [
                strrev($userId),
                $deliveryId,
                sha1($attemptId),
                $tenantId,
            ],
        );
        $this->deliveryExecutionService
            ->method('findDeliveryExecution')
            ->with($id)
            ->willReturn(null);
        $this->deliveryExecutionService
            ->expects($this->never())
            ->method('saveDeliveryExecution');
        ($this->sut)($message);
    }

    public function testItSkipsWhenDeliveryExecutionNotFinished(): void
    {
        $deliveryId = '76f244a88322';
        $sessionId = $attemptId = '18e8c261-c478-4a7e-be99-55468f89aef5';
        $tenantId = '1';
        $userId = 'test-taker-1';
        $message = new ImageResponse(
            'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
            $tenantId,
            new DateTime(),
            'success',
            new Metadata(
                "$sessionId$userId",
                $deliveryId,
                $sessionId,
                $attemptId,
                'item-1',
                'RESPONSE',
                1,
            ),
        );
        $id = implode(
            '#',
            [
                strrev($userId),
                $deliveryId,
                sha1($attemptId),
                $tenantId,
            ],
        );
        $this->deliveryExecutionService
            ->method('findDeliveryExecution')
            ->with($id)
            ->willReturn($this->createTestDeliveryExecution($id, $deliveryId, $tenantId));
        $this->deliveryExecutionService
            ->expects($this->never())
            ->method('saveDeliveryExecution');
        ($this->sut)($message);
    }

    public function testItSkipsForMismatchingTenantId(): void
    {
        $deliveryId = '76f244a88322';
        $sessionId = $attemptId = '18e8c261-c478-4a7e-be99-55468f89aef5';
        $tenantId = '1';
        $userId = 'test-taker-1';
        $message = new ImageResponse(
            'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
            $tenantId,
            new DateTime(),
            'success',
            new Metadata(
                "$sessionId$userId",
                $deliveryId,
                $sessionId,
                $attemptId,
                'item-1',
                'RESPONSE',
                1,
            ),
        );
        $id = implode(
            '#',
            [
                strrev($userId),
                $deliveryId,
                sha1($attemptId),
                $tenantId,
            ],
        );
        $this->deliveryExecutionService
            ->method('findDeliveryExecution')
            ->with($id)
            ->willReturn($this->createTestDeliveryExecution($id, $deliveryId, 'unknown', finishedAt: new DateTime()));
        $this->deliveryExecutionService
            ->expects($this->once())
            ->method('saveDeliveryExecution')
            ->willReturnCallback(function (DeliveryExecution $deliveryExecution): DeliveryExecution {
                $this->assertEmpty(
                    $deliveryExecution->getItemAttachments('item-1'),
                );
                return $deliveryExecution;
            });
        ($this->sut)($message);
    }

    public function testItSetsAttachmentToDeliveryExecution(): void
    {
        $deliveryId = '76f244a88322';
        $sessionId = $attemptId = '18e8c261-c478-4a7e-be99-55468f89aef5';
        $tenantId = '1';
        $userId = 'test-taker-1';
        $message = new ImageResponse(
            'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
            $tenantId,
            new DateTime('2025-12-02T20:46:52+01:00'),
            'success',
            new Metadata(
                "$sessionId$userId",
                $deliveryId,
                $sessionId,
                $attemptId,
                'item-1',
                'RESPONSE',
                1,
            ),
        );
        $id = implode(
            '#',
            [
                strrev($userId),
                $deliveryId,
                sha1($attemptId),
                $tenantId,
            ],
        );
        $this->deliveryExecutionService
            ->method('findDeliveryExecution')
            ->with($id)
            ->willReturn($this->createTestDeliveryExecution($id, $deliveryId, $tenantId, finishedAt: new DateTime()));
        $this->deliveryExecutionService
            ->expects($this->once())
            ->method('saveDeliveryExecution')
            ->willReturnCallback(function (DeliveryExecution $deliveryExecution): DeliveryExecution {
                $this->assertEquals(
                    [
                        [
                            'id' => 'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
                            'responseId' => 'RESPONSE',
                            'createdAt' => '2025-12-02T20:46:52+01:00',
                            'pageNumber' => 1,
                        ],
                    ],
                    $deliveryExecution->getItemAttachments('item-1'),
                );
                return $deliveryExecution;
            });
        ($this->sut)($message);
    }

    public function testItAddsSecondPageAttachmentToDeliveryExecution(): void
    {
        $deliveryId = '76f244a88322';
        $sessionId = $attemptId = '18e8c261-c478-4a7e-be99-55468f89aef5';
        $tenantId = '1';
        $userId = 'test-taker-1';
        $message = new ImageResponse(
            'e79e3cd3-8022-4fd7-a9e1-bcf072639915',
            $tenantId,
            new DateTime('2025-12-02T20:47:28+01:00'),
            'success',
            new Metadata(
                "$sessionId$userId",
                $deliveryId,
                $sessionId,
                $attemptId,
                'item-1',
                'RESPONSE',
                2,
            ),
        );
        $id = implode(
            '#',
            [
                strrev($userId),
                $deliveryId,
                sha1($attemptId),
                $tenantId,
            ],
        );
        $this->deliveryExecutionService
            ->method('findDeliveryExecution')
            ->with($id)
            ->willReturn(
                $this->createTestDeliveryExecution(
                    $id,
                    $deliveryId,
                    $tenantId,
                    finishedAt: new DateTime(),
                )->setAttachments([
                    'item-1' => [
                        [
                            'id' => 'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
                            'responseId' => 'RESPONSE',
                            'createdAt' => '2025-12-02T20:46:52+01:00',
                            'pageNumber' => 1,
                        ],
                    ],
                ]),
            );
        $this->deliveryExecutionService
            ->expects($this->once())
            ->method('saveDeliveryExecution')
            ->willReturnCallback(function (DeliveryExecution $deliveryExecution): DeliveryExecution {
                $this->assertEquals(
                    [
                        [
                            'id' => 'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
                            'responseId' => 'RESPONSE',
                            'createdAt' => '2025-12-02T20:46:52+01:00',
                            'pageNumber' => 1,
                        ],
                        [
                            'id' => 'e79e3cd3-8022-4fd7-a9e1-bcf072639915',
                            'responseId' => 'RESPONSE',
                            'createdAt' => '2025-12-02T20:47:28+01:00',
                            'pageNumber' => 2,
                        ],
                    ],
                    $deliveryExecution->getItemAttachments('item-1'),
                );
                return $deliveryExecution;
            });
        ($this->sut)($message);
    }

    public function testItReplacesPageAttachmentToDeliveryExecution(): void
    {
        $deliveryId = '76f244a88322';
        $sessionId = $attemptId = '18e8c261-c478-4a7e-be99-55468f89aef5';
        $tenantId = '1';
        $userId = 'test-taker-1';
        $message = new ImageResponse(
            '22bc50a0-9892-4732-baa9-3cc9f038162b',
            $tenantId,
            new DateTime('2025-12-02T20:52:31+01:00'),
            'success',
            new Metadata(
                "$sessionId$userId",
                $deliveryId,
                $sessionId,
                $attemptId,
                'item-1',
                'RESPONSE',
                1,
            ),
        );
        $id = implode(
            '#',
            [
                strrev($userId),
                $deliveryId,
                sha1($attemptId),
                $tenantId,
            ],
        );
        $this->deliveryExecutionService
            ->method('findDeliveryExecution')
            ->with($id)
            ->willReturn(
                $this->createTestDeliveryExecution(
                    $id,
                    $deliveryId,
                    $tenantId,
                    finishedAt: new DateTime(),
                )->setAttachments([
                    'item-1' => [
                        [
                            'id' => 'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
                            'responseId' => 'RESPONSE',
                            'createdAt' => '2025-12-02T20:46:52+01:00',
                            'pageNumber' => 1,
                        ],
                        [
                            'id' => 'e79e3cd3-8022-4fd7-a9e1-bcf072639915',
                            'responseId' => 'RESPONSE',
                            'createdAt' => '2025-12-02T20:47:28+01:00',
                            'pageNumber' => 2,
                        ],
                    ],
                ]),
            );
        $this->deliveryExecutionService
            ->expects($this->once())
            ->method('saveDeliveryExecution')
            ->willReturnCallback(function (DeliveryExecution $deliveryExecution): DeliveryExecution {
                $this->assertEquals(
                    [
                        [
                            'id' => '22bc50a0-9892-4732-baa9-3cc9f038162b',
                            'responseId' => 'RESPONSE',
                            'createdAt' => '2025-12-02T20:52:31+01:00',
                            'pageNumber' => 1,
                        ],
                        [
                            'id' => 'e79e3cd3-8022-4fd7-a9e1-bcf072639915',
                            'responseId' => 'RESPONSE',
                            'createdAt' => '2025-12-02T20:47:28+01:00',
                            'pageNumber' => 2,
                        ],
                    ],
                    $deliveryExecution->getItemAttachments('item-1'),
                );
                return $deliveryExecution;
            });
        ($this->sut)($message);
    }

    public function testItRejectsWhenOlderItemResponseIsProvisioned(): void
    {
        $deliveryId = '76f244a88322';
        $sessionId = $attemptId = '18e8c261-c478-4a7e-be99-55468f89aef5';
        $tenantId = '1';
        $userId = 'test-taker-1';
        $message = new ImageResponse(
            'd6bd7c84-095d-4299-aabb-591f3cf9cf05',
            $tenantId,
            new DateTime('2025-12-02T20:46:52+01:00'),
            'success',
            new Metadata(
                "$sessionId$userId",
                $deliveryId,
                $sessionId,
                $attemptId,
                'item-1',
                'RESPONSE',
                1,
            ),
        );
        $id = implode(
            '#',
            [
                strrev($userId),
                $deliveryId,
                sha1($attemptId),
                $tenantId,
            ],
        );
        $this->deliveryExecutionService
            ->method('findDeliveryExecution')
            ->with($id)
            ->willReturn(
                $this->createTestDeliveryExecution(
                    $id,
                    $deliveryId,
                    $tenantId,
                    finishedAt: new DateTime(),
                )->setAttachments([
                    'item-1' => [
                        [
                            'id' => '22bc50a0-9892-4732-baa9-3cc9f038162b',
                            'responseId' => 'RESPONSE',
                            'createdAt' => '2025-12-02T20:52:31+01:00',
                            'pageNumber' => 1,
                        ],
                        [
                            'id' => 'e79e3cd3-8022-4fd7-a9e1-bcf072639915',
                            'responseId' => 'RESPONSE',
                            'createdAt' => '2025-12-02T20:47:28+01:00',
                            'pageNumber' => 2,
                        ],
                    ],
                ]),
            );
        $this->deliveryExecutionService
            ->expects($this->once())
            ->method('saveDeliveryExecution')
            ->willReturnCallback(function (DeliveryExecution $deliveryExecution): DeliveryExecution {
                $this->assertEquals(
                    [
                        [
                            'id' => '22bc50a0-9892-4732-baa9-3cc9f038162b',
                            'responseId' => 'RESPONSE',
                            'createdAt' => '2025-12-02T20:52:31+01:00',
                            'pageNumber' => 1,
                        ],
                        [
                            'id' => 'e79e3cd3-8022-4fd7-a9e1-bcf072639915',
                            'responseId' => 'RESPONSE',
                            'createdAt' => '2025-12-02T20:47:28+01:00',
                            'pageNumber' => 2,
                        ],
                    ],
                    $deliveryExecution->getItemAttachments('item-1'),
                );
                return $deliveryExecution;
            });
        ($this->sut)($message);
    }
}
