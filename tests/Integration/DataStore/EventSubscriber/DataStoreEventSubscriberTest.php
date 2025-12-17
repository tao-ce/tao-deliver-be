<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\DataStore\EventSubscriber;

use App\DataStore\EventSubscriber\DataStoreEventSubscriber;
use App\Environment\FeatureFlagAdapterInterface;
use App\Messenger\Message\DataStoreDeliveryExecutionActionMessage;
use App\Messenger\MessageBus\PostProcessedMessageBusInterface;
use App\TestRunner\Event\DeliveryExecutionCreatedEvent;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use Carbon\Carbon;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DataStoreEventSubscriberTest extends KernelTestCase
{
    use LoggerTestingTrait;
    use MessengerTestingTrait;
    use DomainTestingTrait;

    /** @var DataStoreEventSubscriber */
    private $subject;

    private PostProcessedMessageBusInterface $postProcessedMessageBus;

    protected function setUp(): void
    {
        self::bootKernel();

        Carbon::setTestNow(Carbon::createFromTimestamp(1597248430));

        $this->setUpTestLogHandler();
        $this->setUpTestMessageBus();

        $this->subject = static::getContainer()->get(DataStoreEventSubscriber::class);
        $this->postProcessedMessageBus = static::getContainer()->get(PostProcessedMessageBusInterface::class);
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testOnDeliveryExecutionCreated(): void
    {
        $event = new DeliveryExecutionCreatedEvent($this->createTestDeliveryExecution());

        $this->subject->onDeliveryExecutionCreated($event);
        $this->postProcessedMessageBus->free();

        $this->assertCountTransportMessages('datastore-delivery-execution-actions', 1);
        $this->assertHasTransportMessage('datastore-delivery-execution-actions', DataStoreDeliveryExecutionActionMessage::class);

        $this->assertHasLogRecordWithMessage(
            '[userId#deliveryId#resultId#tenantId] DataStore transfer message a Delivery Execution initialization',
            Logger::INFO,
            'audit_delivery_execution',
        );
    }

    public function testDispatchMessageOnDeliveryExecutionCreated(): void
    {
        $event = new DeliveryExecutionCreatedEvent($this->createTestDeliveryExecution());

        $postMessageBus = $this->createMock(PostProcessedMessageBusInterface::class);
        $postMessageBus
            ->expects(self::once())
            ->method('dispatch');

        $featureFlagAdapter = $this->createMock(FeatureFlagAdapterInterface::class);
        $featureFlagAdapter
            ->method('isEnabled')
            ->willReturn(true);

        $subject = new DataStoreEventSubscriber(
            $postMessageBus,
            static::getContainer()->get(LoggerInterface::class),
            $featureFlagAdapter,
        );
        $subject->onDeliveryExecutionCreated($event);
        $postMessageBus->free();
    }
}
