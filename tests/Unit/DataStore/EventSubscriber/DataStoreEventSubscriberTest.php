<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DataStore\EventSubscriber;

use App\DataStore\EventSubscriber\DataStoreEventSubscriber;
use App\Environment\FeatureFlagAdapterInterface;
use App\Messenger\Message\DataStoreDeliveryExecutionActionMessage;
use App\Messenger\MessageBus\PostProcessedMessageBusInterface;
use App\TestRunner\Event\DeliveryExecutionCreatedEvent;
use App\Tests\Traits\DataStoreTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use Carbon\Carbon;
use Exception;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;

class DataStoreEventSubscriberTest extends KernelTestCase
{
    use DataStoreTestingTrait;
    use LoggerTestingTrait;

    private DataStoreEventSubscriber $subject;
    private PostProcessedMessageBusInterface $messageBusMock;

    protected function setUp(): void
    {
        self::bootKernel();

        Carbon::setTestNow(Carbon::now());

        $this->setUpTestLogHandler();

        $featureFlagRepositoryMock = $this->createMock(FeatureFlagAdapterInterface::class);
        $featureFlagRepositoryMock
            ->method('isEnabled')
            ->willReturn(true);

        $this->messageBusMock = $this->getPostProcessMessageBusMock();
        $this->subject = new DataStoreEventSubscriber(
            $this->messageBusMock,
            static::getContainer()->get('monolog.logger.audit_delivery_execution'),
            $featureFlagRepositoryMock,
        );
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testMessageToDataStoreAdded()
    {
        $deliveryExecution = $this->createTestDeliveryExecution();
        $event = new DeliveryExecutionCreatedEvent($deliveryExecution);

        $this->messageBusMock->expects(self::once())->method('dispatch');

        $this->subject->onDeliveryExecutionCreated($event);
    }

    public function testMessageHasRequiredAttributes()
    {
        $deliveryExecution = $this->createTestDeliveryExecution();
        $event = new DeliveryExecutionCreatedEvent($deliveryExecution);

        $message = new DataStoreDeliveryExecutionActionMessage(
            $deliveryExecution,
            DataStoreDeliveryExecutionActionMessage::ACTION_CREATE,
        );

        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (DataStoreDeliveryExecutionActionMessage $message): bool {
                self::assertEquals('userId#deliveryId#resultId#tenantId', $message->getDeliveryExecutionId());
                self::assertEquals('deliveryId', $message->getDeliveryId());
                self::assertEquals('tenantId', $message->getTenantId());
                self::assertEquals('userId', strrev($message->getUserId()));
                self::assertNull($message->getBatteryId());

                return true;
            }))
            ->willReturn(new Envelope($message));

        $this->subject->onDeliveryExecutionCreated($event);
    }

    public function testMessageHasBatteryId()
    {
        $deliveryExecution = $this->createTestDeliveryExecution(ltiLaunchParameters: ['battery_id' => 'battery-id']);
        $event = new DeliveryExecutionCreatedEvent($deliveryExecution);

        $message = new DataStoreDeliveryExecutionActionMessage(
            $deliveryExecution,
            DataStoreDeliveryExecutionActionMessage::ACTION_CREATE,
        );

        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (DataStoreDeliveryExecutionActionMessage $message): bool {
                self::assertEquals('battery-id', $message->getBatteryId());

                return true;
            }))
            ->willReturn(new Envelope($message));

        $this->subject->onDeliveryExecutionCreated($event);
    }

    public function testOnDeliveryExecutionCreatedAndLogged()
    {
        $deliveryExecution = $this->createTestDeliveryExecution();
        $event = new DeliveryExecutionCreatedEvent($deliveryExecution);

        $message = new DataStoreDeliveryExecutionActionMessage(
            $deliveryExecution,
            DataStoreDeliveryExecutionActionMessage::ACTION_CREATE,
        );

        $this->messageBusMock
             ->expects(self::once())
             ->method('dispatch')
             ->willReturn(new Envelope($message));

        $this->subject->onDeliveryExecutionCreated($event);
        $this->assertHasLogRecordWithMessage(
            sprintf(
                '[%s] DataStore transfer message a Delivery Execution initialization',
                $deliveryExecution->getId(),
            ),
            Logger::INFO,
            'audit_delivery_execution',
        );
    }

    public function testOnDeliveryExecutionCreatedProcessFailed(): void
    {
        $exception = new Exception();
        $deliveryExecution = $this->createTestDeliveryExecution();
        $event = new DeliveryExecutionCreatedEvent($deliveryExecution);

        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->willThrowException($exception);
        $this->expectExceptionObject($exception);

        $this->subject->onDeliveryExecutionCreated($event);
    }
}
