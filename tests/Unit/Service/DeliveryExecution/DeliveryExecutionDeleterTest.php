<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Messenger\Message\DataStoreDeliveryExecutionActionMessage;
use App\Messenger\MessageBus\PostProcessedMessageBusInterface;
use App\Messenger\Stamp\StartTimeStamp;
use App\Repository\DeliveryExecutionRepository;
use App\Service\DeliveryExecution\DeliveryExecutionDeleter;
use App\Service\DeliveryExecution\DeliveryExecutionResultManagerService;
use App\TestRunner\Service\ExternalTimerService;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;

class DeliveryExecutionDeleterTest extends TestCase
{
    private DeliveryExecutionDeleter $subject;
    private PostProcessedMessageBusInterface|MockObject $postProcessedMessageBusMock;
    private DeliveryExecutionRepository|MockObject $deliveryExecutionRepositoryMock;
    private DeliveryExecutionResultManagerService|MockObject $deliveryExecutionResultManagerServiceMock;
    private ExternalTimerService|MockObject $externalTimerServiceMock;

    protected function setUp(): void
    {
        $this->postProcessedMessageBusMock = $this->createMock(PostProcessedMessageBusInterface::class);
        $this->deliveryExecutionRepositoryMock = $this->createMock(DeliveryExecutionRepository::class);
        $this->deliveryExecutionResultManagerServiceMock = $this->createMock(DeliveryExecutionResultManagerService::class);
        $this->externalTimerServiceMock = $this->createMock(ExternalTimerService::class);

        $this->subject = new DeliveryExecutionDeleter(
            $this->postProcessedMessageBusMock,
            $this->deliveryExecutionRepositoryMock,
            $this->deliveryExecutionResultManagerServiceMock,
            $this->externalTimerServiceMock,
        );
    }

    public function testDelete(): void
    {
        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->method('getId')
            ->willReturn('id');
        $deliveryExecutionMock
            ->method('getStartedAt')
            ->willReturn(new DateTimeImmutable());
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('setIsDeleted')
            ->willReturn($deliveryExecutionMock);

        $this->deliveryExecutionRepositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($deliveryExecutionMock);

        $this->deliveryExecutionResultManagerServiceMock
            ->expects($this->once())
            ->method('dropResults')
            ->with('id');

        $this->externalTimerServiceMock
            ->expects($this->once())
            ->method('deleteServerTimer')
            ->with($deliveryExecutionMock);

        $this->postProcessedMessageBusMock
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(DataStoreDeliveryExecutionActionMessage::class),
                $this->callback(function ($array) {
                    return is_array($array)
                        && count($array) === 1
                        && $array[0] instanceof StartTimeStamp;
                }),
            )
            ->willReturn(Envelope::wrap(new DataStoreDeliveryExecutionActionMessage(
                $deliveryExecutionMock,
                DataStoreDeliveryExecutionActionMessage::ACTION_DELETE,
            )));

        $this->subject->delete($deliveryExecutionMock);
    }

    public function testDeleteThrowsRuntimeException(): void
    {
        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('getId')
            ->willReturn('id');
        $deliveryExecutionMock
            ->expects($this->once())
            ->method('setIsDeleted')
            ->willReturn($deliveryExecutionMock);

        $this->deliveryExecutionRepositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($deliveryExecutionMock)
            ->willThrowException(new Exception('reason'));

        $this->deliveryExecutionResultManagerServiceMock
            ->expects($this->never())
            ->method('dropResults');

        $this->externalTimerServiceMock
            ->expects($this->never())
            ->method('deleteServerTimer');

        $this->postProcessedMessageBusMock
            ->expects($this->never())
            ->method('dispatch');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to delete delivery execution with id "id". Reason: reason');

        $this->subject->delete($deliveryExecutionMock);
    }
}
