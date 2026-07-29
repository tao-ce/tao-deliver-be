<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Handler;

use App\Messenger\Handler\DeliveryExecutionValidateRemovedHandler;
use App\Messenger\Message\DataPolicy\ConfirmationMessage;
use App\Messenger\Message\DataPolicy\ValidationConfirmationMessage;
use App\Messenger\Message\DataPolicy\ValidationRequestMessage;
use App\Repository\DeliveryExecutionRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;

class DeliveryExecutionValidateRemovedHandlerTest extends TestCase
{
    private DeliveryExecutionRepository|MockObject $repository;
    private LoggerInterface|MockObject $auditPlatformLogger;
    private MessageBusInterface|MockObject $messageBus;

    private DeliveryExecutionValidateRemovedHandler $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(DeliveryExecutionRepository::class);
        $this->auditPlatformLogger = $this->createMock(LoggerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->subject = new DeliveryExecutionValidateRemovedHandler(
            $this->repository,
            $this->auditPlatformLogger,
            $this->messageBus,
        );
    }

    public function testInvokeReturnsEarlyWhenOwnerAppIsNotDefault(): void
    {
        $message = $this->createMessage(
            ownerApp: 'other-app',
            policyId: 'remove-candidate-delivery-execution',
        );

        $this->repository->expects($this->never())->method('existsForUserIdAndStatuses');
        $this->messageBus->expects($this->never())->method('dispatch');
        $this->auditPlatformLogger->expects($this->never())->method('error');

        $this->subject->__invoke($message);
    }

    public function testInvokeThrowsWhenPolicyIdIsNotMapped(): void
    {
        $message = $this->createMessage(
            ownerApp: ConfirmationMessage::DEFAULT_OWNER_APP,
            policyId: 'unknown-policy',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Incorrect policyId for TYPE_TO_STATUS mapping: unknown-policy');

        $this->subject->__invoke($message);
    }

    public function testInvokeDispatchesValidationConfirmationMessageWhenNoExecutionsExist(): void
    {
        $message = $this->createMessage(
            ownerApp: ConfirmationMessage::DEFAULT_OWNER_APP,
            policyId: 'remove-candidate-delivery-execution',
        );

        $this->repository
            ->expects($this->once())
            ->method('existsForUserIdAndStatuses')
            ->with(
                $message->userId,
                $this->isType('array'),
            )

            ->willReturn(false);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function ($dispatched) use ($message): bool {
                if (!$dispatched instanceof ValidationConfirmationMessage) {
                    return false;
                }

                $expected = ValidationConfirmationMessage::createRemovalConfirmationMessage($message);

                return $dispatched->getTenantId() === $expected->getTenantId()
                    && $dispatched->getDataSubjectRawId() === $expected->getDataSubjectRawId()
                    && $dispatched->getPolicyId() === $expected->getPolicyId()
                    && $dispatched->getPolicyVersion() === $expected->getPolicyVersion()
                    && $dispatched->getOwnerApp() === $expected->getOwnerApp();
            }));

        $this->auditPlatformLogger->expects($this->never())->method('error');

        $this->subject->__invoke($message);
    }


    public function testInvokeDoesNotDispatchWhenExecutionsExist(): void
    {
        $message = $this->createMessage(
            ownerApp: ConfirmationMessage::DEFAULT_OWNER_APP,
            policyId: 'remove-candidate-delivery-execution',
        );

        $this->repository
            ->expects($this->once())
            ->method('existsForUserIdAndStatuses')
            ->willReturn(true);

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->auditPlatformLogger->expects($this->never())->method('error');

        $this->subject->__invoke($message);
    }

    public function testInvokeLogsErrorAndSwallowsRepositoryException(): void
    {
        $message = $this->createMessage(
            ownerApp: ConfirmationMessage::DEFAULT_OWNER_APP,
            policyId: 'remove-candidate-delivery-execution',
        );

        $this->repository
            ->expects($this->once())
            ->method('existsForUserIdAndStatuses')
            ->willThrowException(new RuntimeException('boom'));

        $this->messageBus->expects($this->never())->method('dispatch');

        $this->auditPlatformLogger
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to validate deleted deliveryExecutions'));

        $this->subject->__invoke($message);
    }

    private function createMessage(string $ownerApp, string $policyId, string $type = 'validation.request'): ValidationRequestMessage
    {
        return new ValidationRequestMessage(
            type: $type,
            policyId: $policyId,
            policyVersion: '1',
            tenantId: 'tenant-1',
            ownerApp: $ownerApp,
            userId: 'user-1',
        );
    }

}
