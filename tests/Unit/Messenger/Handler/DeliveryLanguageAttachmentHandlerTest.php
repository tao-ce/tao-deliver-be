<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Handler;

use App\Domain\Delivery\Model\Delivery;
use App\Messenger\Handler\DeliveryLanguageAttachmentHandler;
use App\Messenger\Message\Delivery\DeliveryLanguageAttachmentMessage;
use App\Repository\DeliveryRepository;
use App\Service\Delivery\AttachLanguageToDeliveryService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use RuntimeException;
use Throwable;

class DeliveryLanguageAttachmentHandlerTest extends TestCase
{
    private DeliveryLanguageAttachmentHandler $subject;
    private readonly DeliveryRepository $deliveryRepositoryMock;
    private readonly MessageBusInterface $messageBusMock;
    private readonly AttachLanguageToDeliveryService $attachLanguageToDeliveryServiceMock;

    protected function setUp(): void
    {
        $this->deliveryRepositoryMock = $this->createMock(DeliveryRepository::class);
        $this->messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->attachLanguageToDeliveryServiceMock = $this->createMock(AttachLanguageToDeliveryService::class);

        $this->subject = new DeliveryLanguageAttachmentHandler(
            $this->deliveryRepositoryMock,
            $this->messageBusMock,
            $this->attachLanguageToDeliveryServiceMock,
        );
    }

    public function testHandlerInvokesServiceSuccessfully(): void
    {
        $delivery = $this->createMock(Delivery::class);
        $message = new DeliveryLanguageAttachmentMessage('deliveryId', 'en', 'packagePath');

        $this->deliveryRepositoryMock
            ->method('find')
            ->with('deliveryId')
            ->willReturn($delivery);

        $this->attachLanguageToDeliveryServiceMock->expects($this->once())
            ->method('handleLocaleAttachment')
            ->with(
                $delivery,
                'en',
                'packagePath',
                null,
            );

        $this->subject->__invoke($message);
    }

    public function testWhenServiceThrowsExceptionHandlerPropagatesIt(): void
    {
        $message = new DeliveryLanguageAttachmentMessage('deliveryId', 'en', 'packagePath', null);

        $this->attachLanguageToDeliveryServiceMock->expects($this->once())
            ->method('handleLocaleAttachment')
            ->willThrowException(new RuntimeException('An error occurred.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An error occurred.');

        $this->subject->__invoke($message);
    }
}
