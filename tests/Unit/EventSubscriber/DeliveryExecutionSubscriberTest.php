<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\EventSubscriber\DeliveryExecutionSubscriber;
use App\Lti\LtiCustomSettings;
use App\Messenger\Message\DeliveryExecution\DeliveryExecutionCreatedMessage;
use App\Messenger\MessageBus\PostProcessedMessageBusInterface;
use App\Repository\DeliveryExecutionAlias\Contract\DeliveryExecutionIdentifierAliasRepositoryInterface;
use App\Repository\DeliveryExecutionRepository;
use App\TestRunner\Event\DeliveryExecutionCreatedEvent;
use App\TestRunner\Event\DeliveryExecutionPersistedEvent;
use DateTime;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class DeliveryExecutionSubscriberTest extends TestCase
{
    private DeliveryExecutionSubscriber $subject;
    private MessageBusInterface|MockObject $messageBusMock;
    private PostProcessedMessageBusInterface|MockObject $postProcessedMessageBusMock;
    private DeliveryExecutionIdentifierAliasRepositoryInterface|MockObject $deliveryExecutionExternalAliasRepositoryMock;
    private DeliveryExecutionRepository|MockObject $deliveryExecutionRepositoryMock;
    private LtiCustomSettings|MockObject $ltiCustomSettingsMock;

    protected function setUp(): void
    {
        $this->messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->postProcessedMessageBusMock = $this->createMock(PostProcessedMessageBusInterface::class);
        $this->deliveryExecutionExternalAliasRepositoryMock = $this->createMock(DeliveryExecutionIdentifierAliasRepositoryInterface::class);
        $this->ltiCustomSettingsMock = $this->createMock(LtiCustomSettings::class);
        $this->deliveryExecutionRepositoryMock = $this->createMock(DeliveryExecutionRepository::class);

        $this->subject = new DeliveryExecutionSubscriber(
            $this->messageBusMock,
            $this->postProcessedMessageBusMock,
            $this->deliveryExecutionExternalAliasRepositoryMock,
            $this->ltiCustomSettingsMock,
            $this->deliveryExecutionRepositoryMock,
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $this->assertSame([
            DeliveryExecutionCreatedEvent::class => 'onDeliveryExecutionCreated',
            DeliveryExecutionPersistedEvent::class => 'onDeliveryExecutionPersisted',
        ], $this->subject->getSubscribedEvents());
    }

    public function testOnDeliveryExecutionPersisted(): void
    {
        $deliveryExecution = new DeliveryExecution(
            'id',
            'deliveryId',
            'tenantId',
            new DateTime(),
            [
                'lti' => 'launchParameters',
                'result_id' => 'resultId',
            ],
            'qtiCompactTestFilePath',
            null,
        );

        $this->postProcessedMessageBusMock
            ->expects($this->once())
            ->method('free');

        $this->messageBusMock
            ->expects($this->never())
            ->method('dispatch');

        $this->ltiCustomSettingsMock
            ->expects(self::never())
            ->method('getDeliverExecutionIdAlias')
            ->willReturn(null);

        $this->deliveryExecutionExternalAliasRepositoryMock->expects(self::never())->method('saveDeliveryExecutionId');
        $this->deliveryExecutionExternalAliasRepositoryMock->expects(self::never())->method('findDeliveryExecutionId');

        $this->subject->onDeliveryExecutionPersisted(new DeliveryExecutionPersistedEvent($deliveryExecution));
    }

    public function testOnDeliveryExecutionPersistedAndCreated(): void
    {
        $deliveryExecution = new DeliveryExecution(
            'userId#deliveryId#resultId#tenantId',
            'deliveryId',
            'tenantId',
            new DateTime(),
            [
                'lti' => 'launchParameters',
                'result_id' => 'resultId',
            ],
            'qtiCompactTestFilePath',
            null,
        );

        $this->postProcessedMessageBusMock
            ->expects($this->once())
            ->method('free');

        $this->messageBusMock
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($parameter) {
                return $parameter instanceof DeliveryExecutionCreatedMessage;
            }))
            ->willReturn(new Envelope(new stdClass()));

        $this->ltiCustomSettingsMock
            ->expects(self::exactly(2))
            ->method('getDeliverExecutionIdAlias')
            ->willReturn(null);

        $this->deliveryExecutionExternalAliasRepositoryMock->expects(self::never())->method('saveDeliveryExecutionId');
        $this->deliveryExecutionExternalAliasRepositoryMock->expects(self::never())->method('findDeliveryExecutionId');

        $this->subject->onDeliveryExecutionCreated(new DeliveryExecutionCreatedEvent($deliveryExecution));
        $this->subject->onDeliveryExecutionPersisted(new DeliveryExecutionPersistedEvent($deliveryExecution));
    }

    public function testOnDeliveryExecutionPersistedAndCreatedWithDeliveryExecutionIdentifierAlias(): void
    {
        $deliveryExecution = new DeliveryExecution(
            'userId#deliveryId#resultId#tenantId',
            'deliveryId',
            'tenantId',
            new DateTime(),
            [
                'lti' => 'launchParameters',
                'result_id' => 'resultId',
            ],
            'qtiCompactTestFilePath',
            null,
        );

        $this->postProcessedMessageBusMock
            ->expects($this->once())
            ->method('free');

        $this->messageBusMock
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($parameter) {
                return $parameter instanceof DeliveryExecutionCreatedMessage;
            }))
            ->willReturn(new Envelope(new stdClass()));

        $this->ltiCustomSettingsMock
            ->expects(self::exactly(2))
            ->method('getDeliverExecutionIdAlias')
            ->willReturn('alias');

        $this->deliveryExecutionExternalAliasRepositoryMock
            ->expects(self::once())
            ->method('saveDeliveryExecutionId')
            ->with('tenantId', 'alias', 'userId#deliveryId#resultId#tenantId');

        $this->deliveryExecutionExternalAliasRepositoryMock
            ->expects(self::once())
            ->method('findDeliveryExecutionId')
            ->with('tenantId', 'alias')
            ->willReturn(null);

        $this->subject->onDeliveryExecutionCreated(new DeliveryExecutionCreatedEvent($deliveryExecution));
        $this->subject->onDeliveryExecutionPersisted(new DeliveryExecutionPersistedEvent($deliveryExecution));
    }

    public function testOnDeliveryExecutionCreatedWithDeliveryExecutionIdentifierAlias(): void
    {
        $deliveryExecution = new DeliveryExecution(
            'userId#deliveryId#resultId#tenantId',
            'deliveryId',
            'tenantId',
            new DateTime(),
            [
                'lti' => 'launchParameters',
                'result_id' => 'resultId',
            ],
            'qtiCompactTestFilePath',
            null,
        );

        $this->ltiCustomSettingsMock
            ->expects(self::exactly(1))
            ->method('getDeliverExecutionIdAlias')
            ->willReturn('alias');

        $this->deliveryExecutionExternalAliasRepositoryMock
            ->expects(self::never())
            ->method('saveDeliveryExecutionId')
            ->with('tenantId', 'alias', 'id');

        $this->deliveryExecutionExternalAliasRepositoryMock
            ->expects(self::once())
            ->method('findDeliveryExecutionId')
            ->with('tenantId', 'alias')
            ->willReturn('notId');

        $this->expectException(BadRequestHttpException::class);
        $this->subject->onDeliveryExecutionCreated(new DeliveryExecutionCreatedEvent($deliveryExecution));
    }
}
