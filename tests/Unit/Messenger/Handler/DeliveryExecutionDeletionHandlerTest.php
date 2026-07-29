<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA ;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Handler;

use App\Messenger\Handler\DeliveryExecutionDeletionHandler;
use App\Messenger\Message\DataPolicy\ConfirmationMessage;
use App\Messenger\Message\DataPolicy\RemovalConfirmationMessage;
use App\Messenger\Message\DataPolicy\RemovalRequestMessage;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionDeletionServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;

class DeliveryExecutionDeletionHandlerTest extends TestCase
{
    private DeliveryExecutionDeletionServiceInterface|MockObject $deliveryExecutionDeletionService;
    private LoggerInterface|MockObject $auditPlatformLogger;
    private MessageBusInterface|MockObject $messageBus;

    private DeliveryExecutionDeletionHandler $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deliveryExecutionDeletionService = $this->createMock(DeliveryExecutionDeletionServiceInterface::class);
        $this->auditPlatformLogger = $this->createMock(LoggerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->subject = new DeliveryExecutionDeletionHandler(
            $this->deliveryExecutionDeletionService,
            $this->auditPlatformLogger,
            $this->messageBus,
        );
    }

    public function testInvokeDropsDeliveryExecutionsAndLogsInfo(): void
    {
        $deliveryExecutionIds = ['id1', 'id2', 'id3'];
        $messageType = ConfirmationMessage::DEFAULT_OWNER_APP;

        $message = new RemovalRequestMessage(
            type: $messageType,
            policyId: 'policy-id',
            policyVersion: '1.0',
            userId: 'user-id',
            tenantId: 'tenant-id',
            uniqueId: 'unique-id',
            storageType: 'storage-type',
            ownerApp: ConfirmationMessage::DEFAULT_OWNER_APP,
            deliveryExecutionIds: $deliveryExecutionIds,
            name: 'test-name',
        );

        // Validate request body basics (keeps tests aligned with message contract)
        self::assertSame('policy-id', $message->policyId);
        self::assertSame('1.0', $message->policyVersion);
        self::assertSame('user-id', $message->userId);
        self::assertSame('tenant-id', $message->tenantId);
        self::assertSame('unique-id', $message->uniqueId);
        self::assertSame('storage-type', $message->storageType);
        self::assertSame(ConfirmationMessage::DEFAULT_OWNER_APP, $message->ownerApp);
        self::assertSame($deliveryExecutionIds, $message->deliveryExecutionIds);

        // Validate removal confirmation message body (uniqueId + status + errors + storageType)
        $confirmation = RemovalConfirmationMessage::createRemovalConfirmationMessage(
            $message,
            ConfirmationMessage::STATUS_REMOVED,
            [],
            null,
        );
        self::assertSame('unique-id', $confirmation->getUniqueId());
        self::assertSame(ConfirmationMessage::STATUS_REMOVED, $confirmation->getStatus());
        self::assertSame([], $confirmation->getErrors());
        self::assertSame('storage-type', $confirmation->getStorageType());
        self::assertSame('tenant-id', $confirmation->getTenantId());
        self::assertSame('user-id', $confirmation->getDataSubjectRawId());
        self::assertSame('policy-id', $confirmation->getPolicyId());
        self::assertSame('1.0', $confirmation->getPolicyVersion());
        self::assertSame(ConfirmationMessage::DEFAULT_OWNER_APP, $confirmation->getOwnerApp());


        // Expect removeDeliveryExecutionById to be called for each ID
        $this->deliveryExecutionDeletionService
            ->expects($this->exactly(count($deliveryExecutionIds)))
            ->method('removeDeliveryExecutionById')
            ->withConsecutive(
                ['id1'],
                ['id2'],
                ['id3'],
            );

        $logged = [];

        // Expect info to be logged for each ID
        $this->auditPlatformLogger
            ->expects($this->exactly(count($deliveryExecutionIds)))
            ->method('info')
            ->willReturnCallback(static function (string $message) use (&$logged): void {
                $logged[] = $message;
            });

        $this->messageBus
          ->expects($this->once())
          ->method('dispatch')
          ->with($this->callback(static function ($message): bool {
              return $message instanceof RemovalConfirmationMessage
                  && $message->getStatus() === ConfirmationMessage::STATUS_REMOVED
                  && $message->getErrors() === [];
          }));

        $this->subject->__invoke($message);

        self::assertSame(
            [
                sprintf('DeliveryExecution %s deleted for cleanup message type %s', 'id1', $messageType),
                sprintf('DeliveryExecution %s deleted for cleanup message type %s', 'id2', $messageType),
                sprintf('DeliveryExecution %s deleted for cleanup message type %s', 'id3', $messageType),
            ],
            $logged,
        );
    }

    public function testInvokeHandlesEmptyDeliveryExecutionIdList(): void
    {
        $deliveryExecutionIds = [];
        $messageType = ConfirmationMessage::DEFAULT_OWNER_APP;

        $message = new RemovalRequestMessage(
            type: $messageType,
            policyId: 'policy-id',
            policyVersion: '1.0',
            userId: 'user-id',
            tenantId: 'tenant-id',
            uniqueId: 'unique-id',
            storageType: 'storage-type',
            ownerApp: ConfirmationMessage::DEFAULT_OWNER_APP,
            deliveryExecutionIds: $deliveryExecutionIds,
            name: 'test-name',
        );

        // Expect removeDeliveryExecutionById not to be called
        $this->deliveryExecutionDeletionService
            ->expects($this->never())
            ->method('removeDeliveryExecutionById');

        // Expect info not to be logged
        $this->auditPlatformLogger
            ->expects($this->never())
            ->method('info');

        $this->subject->__invoke($message);
    }

    public function testSkipOtherMessageTypes(): void
    {
        $deliveryExecutionIds = [];
        $messageType = 'other-type';

        $message = new RemovalRequestMessage(
            type: $messageType,
            policyId: 'policy-id',
            policyVersion: '1.0',
            userId: 'user-id',
            tenantId: 'tenant-id',
            uniqueId: 'unique-id',
            storageType: 'storage-type',
            ownerApp: ConfirmationMessage::DEFAULT_OWNER_APP,
            deliveryExecutionIds: $deliveryExecutionIds,
            name: 'test-name',
        );

        // Expect removeDeliveryExecutionById not to be called
        $this->deliveryExecutionDeletionService
            ->expects($this->never())
            ->method('removeDeliveryExecutionById');

        // Expect info not to be logged
        $this->auditPlatformLogger
            ->expects($this->never())
            ->method('info');

        $this->subject->__invoke($message);
    }

    public function testSkipOtherOwnerApps(): void
    {
        $deliveryExecutionIds = ['id1'];
        $messageType = ConfirmationMessage::DEFAULT_OWNER_APP;

        $message = new RemovalRequestMessage(
            type: $messageType,
            policyId: 'policy-id',
            policyVersion: '1.0',
            userId: 'user-id',
            tenantId: 'tenant-id',
            uniqueId: 'unique-id',
            storageType: 'storage-type',
            ownerApp: 'some_other_app',
            deliveryExecutionIds: $deliveryExecutionIds,
            name: 'test-name',
        );

        $this->deliveryExecutionDeletionService
            ->expects($this->never())
            ->method('removeDeliveryExecutionById');

        $this->auditPlatformLogger
            ->expects($this->never())
            ->method('info');

        $this->auditPlatformLogger
            ->expects($this->never())
            ->method('error');

        $this->messageBus
            ->expects($this->never())
            ->method('dispatch');

        $this->subject->__invoke($message);
    }


    public function testInvokeLogsErrorWhenServiceFails(): void
    {
        $deliveryExecutionIds = ['id1'];
        $messageType = ConfirmationMessage::DEFAULT_OWNER_APP;

        $message = new RemovalRequestMessage(
            type: $messageType,
            policyId: 'policy-id',
            policyVersion: '1.0',
            userId: 'user-id',
            tenantId: 'tenant-id',
            uniqueId: 'unique-id',
            storageType: 'storage-type',
            ownerApp: ConfirmationMessage::DEFAULT_OWNER_APP,
            deliveryExecutionIds: $deliveryExecutionIds,
            name: 'test-name',
        );

        $this->deliveryExecutionDeletionService
            ->expects($this->once())
            ->method('removeDeliveryExecutionById')
            ->with('id1')
            ->willThrowException(new RuntimeException('Failed to drop delivery execution'));

        $this->auditPlatformLogger
            ->expects($this->never())
            ->method('info');

        $this->auditPlatformLogger
            ->expects($this->once())
            ->method('error')
            ->with(sprintf(
                'DeliveryExecution %s failed to delete for cleanup message type %s; Error: %s',
                'id1',
                $messageType,
                'Failed to drop delivery execution',
            ));

        $this->subject->__invoke($message);
    }


    public function testInvokeLogsCorrectlyWhenOnlyOneIdIsProvided(): void
    {
        $deliveryExecutionIds = ['single_id'];
        $messageType = ConfirmationMessage::DEFAULT_OWNER_APP;

        $message = new RemovalRequestMessage(
            type: $messageType,
            policyId: 'policy-id',
            policyVersion: '1.0',
            userId: 'user-id',
            tenantId: 'tenant-id',
            uniqueId: 'unique-id',
            storageType: 'storage-type',
            ownerApp: ConfirmationMessage::DEFAULT_OWNER_APP,
            deliveryExecutionIds: $deliveryExecutionIds,
            name: 'test-name',
        );



        $this->deliveryExecutionDeletionService
            ->expects($this->once())
            ->method('removeDeliveryExecutionById')
            ->with('single_id');

        $this->auditPlatformLogger
            ->expects($this->once())
            ->method('info')
            ->with(sprintf('DeliveryExecution %s deleted for cleanup message type %s', 'single_id', $messageType));

        $this->subject->__invoke($message);
    }

    public function testInvokeDispatchesRemovedStatusWhenDeliveryExecutionDoesNotExist(): void
    {
        $message = new RemovalRequestMessage(
            type: ConfirmationMessage::DEFAULT_OWNER_APP,
            policyId: 'policy-id',
            policyVersion: '1.0',
            userId: 'user-id',
            tenantId: 'tenant-id',
            uniqueId: 'unique-id',
            storageType: 'storage-type',
            ownerApp: ConfirmationMessage::DEFAULT_OWNER_APP,
            deliveryExecutionIds: ['non-existent-id'],
            name: 'test-name',
        );

        $this->deliveryExecutionDeletionService
            ->expects($this->once())
            ->method('removeDeliveryExecutionById')
            ->with('non-existent-id');

        $this->auditPlatformLogger
            ->expects($this->never())
            ->method('error');

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function ($message): bool {
                return $message instanceof RemovalConfirmationMessage
                    && $message->getStatus() === ConfirmationMessage::STATUS_REMOVED
                    && $message->getErrors() === [];
            }));

        $this->subject->__invoke($message);
    }

    public function testInvokeDispatchesFailedStatusWithErrorsAsArray(): void
    {
        $message = new RemovalRequestMessage(
            type: ConfirmationMessage::DEFAULT_OWNER_APP,
            policyId: 'policy-id',
            policyVersion: '1.0',
            userId: 'user-id',
            tenantId: 'tenant-id',
            uniqueId: 'unique-id',
            storageType: 'storage-type',
            ownerApp: ConfirmationMessage::DEFAULT_OWNER_APP,
            deliveryExecutionIds: ['id1', 'id2'],
            name: 'test-name',
        );

        $this->deliveryExecutionDeletionService
            ->method('removeDeliveryExecutionById')
            ->willReturnCallback(static function (string $id): void {
                if ($id === 'id1') {
                    throw new RuntimeException('Deletion failed for id1');
                }
            });

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function ($message): bool {
                return $message instanceof RemovalConfirmationMessage
                    && $message->getStatus() === ConfirmationMessage::STATUS_FAILED
                    && $message->getErrors() === ['Deletion failed for id1']
                    && array_is_list($message->getErrors());
            }));

        $this->subject->__invoke($message);
    }
}
