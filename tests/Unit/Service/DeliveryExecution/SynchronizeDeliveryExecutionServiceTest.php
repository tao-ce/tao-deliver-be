<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Helper\Date;
use App\Messenger\Message\DeliveryExecution\NormalizedExecutionControlMessage;
use App\Messenger\Message\DeliveryExecutionUIEventMessage;
use App\Qti\Compiler\QtiPackageCompiler;
use App\Service\DeliveryExecution\DeliveryExecutionFactory;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use App\Service\DeliveryExecution\Dto\DeliveryExecutionDto;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Service\DeliveryExecution\SynchronizeDeliveryExecutionService;
use App\TestRunner\Event\DeliveryExecutionCreatedEvent;
use App\TestRunner\Event\TestSessionEndEvent;
use App\TestRunner\Service\DeliveryExecutionClosureService;
use App\Traits\FilesystemTrait;
use InvalidArgumentException;
use League\Flysystem\FilesystemReader;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class SynchronizeDeliveryExecutionServiceTest extends TestCase
{
    use FilesystemTrait;

    private const UI_EVENTS = [
        [
            'metadata' => [
                'c' => 0,
                'id' => 'recording-1',
                'data' => [
                    'auto' => true,
                    'idx' => 1,
                ],
                'event_name' => 'select',
                'timeStamp' => 1694515268509,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 1,
                'id' => 'P1M813',
                'event_name' => 'start',
                'timeStamp' => 1694515268525,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 2,
                'id' => 'timer.set',
                'data' => 900,
                'event_name' => 'auto',
                'timeStamp' => 1694515268526,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 3,
                'id' => 'play-recoding-btn',
                'data' => [
                    'auto' => true,
                    'idx' => 1,
                ],
                'event_name' => 'click',
                'timeStamp' => 1694515273512,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 4,
                'id' => 'top-nav.next-btn',
                'event_name' => 'click',
                'timeStamp' => 1694515277641,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 5,
                'id' => 'confirm-submit-dialog',
                'event_name' => 'show',
                'timeStamp' => 1694515277642,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 6,
                'id' => 'confirm-submit-dialog.next-btn',
                'event_name' => 'click',
                'timeStamp' => 1694515278708,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 7,
                'id' => 'confirm-submit-dialog',
                'event_name' => 'hide',
                'timeStamp' => 1694515278709,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 8,
                'id' => 'solution-evaluation-dialog',
                'event_name' => 'show',
                'timeStamp' => 1694515278709,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 9,
                'id' => 'P1M813EV',
                'data' => '3',
                'event_name' => 'select',
                'timeStamp' => 1694515279976,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 10,
                'id' => 'solution-evaluation-dialog',
                'event_name' => 'hide',
                'timeStamp' => 1694515280706,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 11,
                'id' => 'solution-dialog',
                'event_name' => 'show',
                'timeStamp' => 1694515280707,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 12,
                'id' => 'top-nav.next-btn',
                'event_name' => 'click',
                'timeStamp' => 1694515286801,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 13,
                'id' => 'solution-dialog',
                'event_name' => 'hide',
                'timeStamp' => 1694515286813,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'metadata' => [
                'c' => 14,
                'id' => 'P1M813',
                'event_name' => 'end',
                'timeStamp' => 1694515286813,
            ],
            'domEventType' => 'feedtrace',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => 'RESPONSE',
        ],
        [
            'domEventType' => 'custom',
            'itemIdentifier' => 'item-9',
            'responseIdentifier' => null,
            'metadata' => [
                'type' => 'move',
                'scope' => 'item',
                'timeStamp' => 1694515286819,
                'direction' => 'next',
                'response' => [
                    'RESPONSE' => [
                        'base' => [
                            'string' => '{"id":"P1M813","ts":1694515286819,"response":[{"id":"P1M813A","type":"multiple_choice","learner_response":""},{"id":"P1M813B","type":"multiple_choice","learner_response":""},{"id":"P1M813EV","type":"multiple_choice","learner_response":"3"}],"common":{"units.DolphinCall.1.timeElapsed":18}}',
                        ],
                    ],
                ],
            ],
        ],
    ];
    private const ASSESSMENT_EVENTS = [
        [
            'actorIdentity' => [
                'id' => '1',
                'name' => 'Test Taker',
                'role' => 'test-taker',
                'userAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5.2 Safari/605.1.15',
                'ip' => '127.0.0.1',
            ],
            'action' => [
                'type' => 'start',
                'status' => 'succeeded',
            ],
            'timestamp' => 1694517414,
            'deliveryExecution' => [
                'id' => 'userId#deliveryId#resultId#tenantId',
                'status' => 'initial',
            ],
            'resourceLink' => [
                'identifier' => '9ec4812a-374c-4f66-b8fc-972c6c6d31b0',
            ],
            'itemId' => 'item-1',
            'reason' => null,
        ],
        [
            'actorIdentity' => [
                'id' => '10',
                'name' => 'Classroom Proctor',
                'role' => 'proctor',
                'userAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5.2 Safari/605.1.15',
                'ip' => '127.0.0.2',
            ],
            'action' => [
                'type' => 'flag',
                'status' => 'succeeded',
            ],
            'timestamp' => 1694517414,
            'deliveryExecution' => [
                'id' => 'userId#deliveryId#resultId#tenantId',
                'status' => 'interacting',
            ],
            'resourceLink' => [
                'identifier' => '9ec4812a-374c-4f66-b8fc-972c6c6d31b0',
            ],
            'itemId' => 'item-2',
            'reason' => [
                'code' => 999,
                'message' => null,
            ],
        ],
    ];
    private const EXPECTED_TENANT_ID = 'tenant-id';
    private const EXPECTED_DEFAULT_DATA = [
        "deliveryExecutionId" => "mode#userId#deliveryId#attemptId#tenant-id",
        "ltiLaunchParameters" => [
            "result_id" => "mode#userId#deliveryId#attemptId#tenant-id",
        ],
        "status" => "initial",
        "startedAt" => "2023-07-11T16:29:24.016+02:00",
    ];

    private SynchronizeDeliveryExecutionService $subject;
    private DeliveryExecutionFactory $deliveryExecutionFactoryMock;
    private EventDispatcherInterface $eventDispatcherMock;
    private MessageBusInterface $messageBusMock;
    private FilesystemReader $qtiCompiledDeliveriesStorageMock;
    private LoggerInterface $auditDeliveryExecutionLoggerMock;
    private DeliveryExecutionClosureService $deliveryExecutionClosureServiceMock;
    private DeliveryExecutionPropertyService $deliveryExecutionPropertyServiceMock;

    protected function setUp(): void
    {
        $this->deliveryExecutionFactoryMock = $this->createMock(DeliveryExecutionFactory::class);
        $deliveryExecutionServiceMock = $this->createMock(DeliveryExecutionService::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->qtiCompiledDeliveriesStorageMock = $this->createMock(FilesystemReader::class);
        $this->auditDeliveryExecutionLoggerMock = $this->createMock(LoggerInterface::class);
        $this->deliveryExecutionClosureServiceMock = $this->createMock(DeliveryExecutionClosureService::class);
        $this->deliveryExecutionPropertyServiceMock = $this->createMock(DeliveryExecutionPropertyService::class);

        $this->subject = new SynchronizeDeliveryExecutionService(
            $this->deliveryExecutionFactoryMock,
            $deliveryExecutionServiceMock,
            $this->eventDispatcherMock,
            $this->messageBusMock,
            $this->qtiCompiledDeliveriesStorageMock,
            $this->deliveryExecutionClosureServiceMock,
            $this->auditDeliveryExecutionLoggerMock,
            $this->deliveryExecutionPropertyServiceMock,
        );
    }

    public function testCreateSuccessfullyNotFinishedDeliveryExecution(): void
    {
        $createDeliveryExecutionDto = DeliveryExecutionDto::createFromArray(self::EXPECTED_DEFAULT_DATA);
        $deliverExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliverExecutionMock->expects($this->atLeastOnce())->method('isStateFinal')->willReturn(false);
        $deliverExecutionMock->expects($this->once())->method('getTenantId')->willReturn(self::EXPECTED_TENANT_ID);

        $this->auditDeliveryExecutionLoggerMock
            ->expects(self::exactly(2))
            ->method('info')
            ->withConsecutive(
                ['[mode#userId#deliveryId#attemptId#tenant-id] Synchronizing DeliveryExecution'],
                ['[mode#userId#deliveryId#attemptId#tenant-id] DeliveryExecution successfully stored'],
            );

        $this->qtiCompiledDeliveriesStorageMock
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->with(new DeliveryExecutionCreatedEvent($deliverExecutionMock));
        $this->deliveryExecutionFactoryMock
            ->expects($this->once())
            ->method('createFromDeliveryExecutionDto')
            ->with($createDeliveryExecutionDto)
            ->willReturn($deliverExecutionMock);

        $this->deliveryExecutionClosureServiceMock
            ->expects(self::exactly(1))
            ->method('close')
            ->with($deliverExecutionMock)
            ->willReturn(false);

        $this->subject->synchronize($createDeliveryExecutionDto, self::EXPECTED_TENANT_ID);
    }

    public function testCreateFailedForDeliveryExecutionForWrongTenant(): void
    {
        $deliveryExecutionDto = DeliveryExecutionDto::createFromArray(self::EXPECTED_DEFAULT_DATA);
        $deliverExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliverExecutionMock->expects($this->never())->method('isStateFinal');
        $deliverExecutionMock->expects($this->once())->method('getTenantId')->willReturn(self::EXPECTED_TENANT_ID);
        $deliverExecutionMock->expects(self::once())->method('getId')->willReturn(
            'mode#userId#deliveryId#attemptId#tenant-id',
        );

        $this->auditDeliveryExecutionLoggerMock
            ->expects(self::exactly(1))
            ->method('info')
            ->withConsecutive(
                ['[mode#userId#deliveryId#attemptId#tenant-id] Synchronizing DeliveryExecution'],
            );

        $this->qtiCompiledDeliveriesStorageMock
            ->expects($this->never())
            ->method('has');

        $this->eventDispatcherMock->expects($this->never())->method('dispatch');
        $this->deliveryExecutionFactoryMock
            ->expects($this->once())
            ->method('createFromDeliveryExecutionDto')
            ->with($deliveryExecutionDto)
            ->willReturn($deliverExecutionMock);

        $this->deliveryExecutionClosureServiceMock
            ->expects(self::never())
            ->method('close');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            '[mode#userId#deliveryId#attemptId#tenant-id][wrong-tenant-id] DeliveryExecution does not belong to the tenant',
        );
        $this->subject->synchronize($deliveryExecutionDto, 'wrong-tenant-id');
    }

    public function testSynchronizedSuccessfullyInteractedDeliveryExecution(): void
    {
        $isClosedByService = true;
        $data = array_merge(
            self::EXPECTED_DEFAULT_DATA,
            [
                'status' => 'interacting',
            ],
        );
        $deliveryExecutionDto = DeliveryExecutionDto::createFromArray($data);
        $deliverExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliverExecutionMock
            ->expects($this->atLeastOnce())
            ->method('isStateFinal')
            ->willReturnCallback(fn() => $isClosedByService);
        $deliverExecutionMock
            ->expects($this->atLeastOnce())
            ->method('getFinishedAt')
            ->willReturn(Date::createFromDefaultFormat('2023-07-11T16:29:24.016+02:00'));
        $deliverExecutionMock->expects($this->once())->method('getTenantId')->willReturn(self::EXPECTED_TENANT_ID);

        $this->auditDeliveryExecutionLoggerMock
            ->expects(self::once())
            ->method('info')
            ->with('[mode#userId#deliveryId#attemptId#tenant-id] Synchronizing DeliveryExecution');

        $this->eventDispatcherMock
            ->expects($this->exactly(1))
            ->method('dispatch')
            ->with(new DeliveryExecutionCreatedEvent($deliverExecutionMock));

        $this->qtiCompiledDeliveriesStorageMock
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $this->deliveryExecutionFactoryMock
            ->expects($this->once())
            ->method('createFromDeliveryExecutionDto')
            ->with($deliveryExecutionDto)
            ->willReturn($deliverExecutionMock);

        $this->deliveryExecutionClosureServiceMock
            ->expects(self::exactly(1))
            ->method('close')
            ->with($deliverExecutionMock)
            ->willReturnCallback(function (DeliveryExecution $deliveryExecution) use (&$isClosedByService) {
                $isClosedByService = true;
                return true;
            });

        $this->subject->synchronize($deliveryExecutionDto, self::EXPECTED_TENANT_ID);
    }

    public function testSynchronizedSuccessfullyInteractedDeliveryExecutionWithUiLogs(): void
    {
        $isClosedByService = true;
        $data = array_merge(
            self::EXPECTED_DEFAULT_DATA,
            [
                'status' => 'interacting',
                'extraStateData' => ['uiEvents' => self::UI_EVENTS],
            ],
        );
        $deliveryExecutionDto = DeliveryExecutionDto::createFromArray($data);
        $deliverExecutionMock = $this->createMock(DeliveryExecution::class);
        $uiEventMessage = new DeliveryExecutionUIEventMessage($deliverExecutionMock, self::UI_EVENTS);
        $deliverExecutionMock
            ->method('getId')
            ->willReturn('mode#userId#deliveryId#attemptId#tenant-id');
        $deliverExecutionMock
            ->expects($this->atLeastOnce())
            ->method('isStateFinal')
            ->willReturnCallback(fn() => $isClosedByService);
        $deliverExecutionMock
            ->expects($this->atLeastOnce())
            ->method('getFinishedAt')
            ->willReturn(Date::createFromDefaultFormat('2023-07-11T16:29:24.016+02:00'));
        $deliverExecutionMock->expects($this->once())->method('getTenantId')->willReturn(self::EXPECTED_TENANT_ID);
        $deliverExecutionMock->method('hasUiEvents')->willReturn(true);
        $deliverExecutionMock
            ->method('popAllUiEvents')
            ->willReturn($uiEventMessage);

        $this->auditDeliveryExecutionLoggerMock
            ->expects(self::exactly(2))
            ->method('info')
            ->withConsecutive(
                ['[mode#userId#deliveryId#attemptId#tenant-id] Synchronizing DeliveryExecution'],
                [
                    sprintf(
                        '[mode#userId#deliveryId#attemptId#tenant-id] - received %u UI events',
                        count(self::UI_EVENTS),
                    ),
                ],
            );

        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->with(new DeliveryExecutionCreatedEvent($deliverExecutionMock));

        $this->qtiCompiledDeliveriesStorageMock
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $this->deliveryExecutionFactoryMock
            ->expects($this->once())
            ->method('createFromDeliveryExecutionDto')
            ->with($deliveryExecutionDto)
            ->willReturn($deliverExecutionMock);

        $this->deliveryExecutionClosureServiceMock
            ->expects($this->once())
            ->method('close')
            ->with($deliverExecutionMock)
            ->willReturnCallback(function (DeliveryExecution $deliveryExecution) use (&$isClosedByService) {
                $isClosedByService = true;
                return true;
            });

        $this->messageBusMock
            ->expects($this->once())
            ->method('dispatch')
            ->with($uiEventMessage)
            ->willReturn(Envelope::wrap($uiEventMessage));

        $this->subject->synchronize($deliveryExecutionDto, self::EXPECTED_TENANT_ID);
    }

    public function testSynchronizedSuccessfullyInteractedDeliveryExecutionWithAssessmentEvents(): void
    {
        $isClosedByService = true;
        $data = array_merge(
            self::EXPECTED_DEFAULT_DATA,
            [
                'status' => 'interacting',
                'extraStateData' => ['assessmentEvents' => self::ASSESSMENT_EVENTS],
            ],
        );
        $deliveryExecutionDto = DeliveryExecutionDto::createFromArray($data);
        $deliverExecutionMock = $this->createMock(DeliveryExecution::class);
        $assessmentEventMessages = array_map(
            static fn(
                array $normalizedAssessmentEventMessage,
            ): NormalizedExecutionControlMessage => new NormalizedExecutionControlMessage(
                $deliverExecutionMock,
                $normalizedAssessmentEventMessage,
            ),
            self::ASSESSMENT_EVENTS,
        );
        $deliverExecutionMock
            ->method('getId')
            ->willReturn('mode#userId#deliveryId#attemptId#tenant-id');
        $deliverExecutionMock
            ->expects($this->atLeastOnce())
            ->method('isStateFinal')
            ->willReturnCallback(fn() => $isClosedByService);
        $deliverExecutionMock
            ->expects($this->atLeastOnce())
            ->method('getFinishedAt')
            ->willReturn(Date::createFromDefaultFormat('2023-07-11T16:29:24.016+02:00'));
        $deliverExecutionMock->expects($this->once())->method('getTenantId')->willReturn(self::EXPECTED_TENANT_ID);
        $deliverExecutionMock->method('hasAssessmentEvents')->willReturn(true);
        $deliverExecutionMock
            ->method('popAllAssessmentEvents')
            ->willReturn($assessmentEventMessages);

        $this->auditDeliveryExecutionLoggerMock
            ->expects(self::exactly(2))
            ->method('info')
            ->withConsecutive(
                ['[mode#userId#deliveryId#attemptId#tenant-id] Synchronizing DeliveryExecution'],
                [
                    sprintf(
                        '[mode#userId#deliveryId#attemptId#tenant-id] - received %u assessment-control events',
                        count($assessmentEventMessages),
                    ),
                ],
            );

        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->with(new DeliveryExecutionCreatedEvent($deliverExecutionMock));

        $this->qtiCompiledDeliveriesStorageMock
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $this->deliveryExecutionFactoryMock
            ->expects($this->once())
            ->method('createFromDeliveryExecutionDto')
            ->with($deliveryExecutionDto)
            ->willReturn($deliverExecutionMock);

        $this->deliveryExecutionClosureServiceMock
            ->expects($this->once())
            ->method('close')
            ->with($deliverExecutionMock)
            ->willReturnCallback(function (DeliveryExecution $deliveryExecution) use (&$isClosedByService) {
                $isClosedByService = true;
                return true;
            });

        $this->messageBusMock
            ->expects($this->exactly(count($assessmentEventMessages)))
            ->method('dispatch')
            ->withConsecutive(
                ...array_map(
                    static fn(NormalizedExecutionControlMessage $message): array => [$message],
                    $assessmentEventMessages,
                ),
            )
            ->willReturnOnConsecutiveCalls(...array_map([Envelope::class, 'wrap'], $assessmentEventMessages));

        $this->subject->synchronize($deliveryExecutionDto, self::EXPECTED_TENANT_ID);
    }

    public function testSynchronizedSuccessfullyInteractedDeliveryExecutionWithUiAndAssessmentEvents(): void
    {
        $isClosedByService = true;
        $data = array_merge(
            self::EXPECTED_DEFAULT_DATA,
            [
                'status' => 'interacting',
                'extraStateData' => ['uiEvents' => self::UI_EVENTS, 'assessmentEvents' => self::ASSESSMENT_EVENTS],
            ],
        );
        $deliveryExecutionDto = DeliveryExecutionDto::createFromArray($data);
        $deliverExecutionMock = $this->createMock(DeliveryExecution::class);
        $uiEventMessage = new DeliveryExecutionUIEventMessage($deliverExecutionMock, self::UI_EVENTS);
        $assessmentEventMessages = array_map(
            static fn(
                array $normalizedAssessmentEventMessage,
            ): NormalizedExecutionControlMessage => new NormalizedExecutionControlMessage(
                $deliverExecutionMock,
                $normalizedAssessmentEventMessage,
            ),
            self::ASSESSMENT_EVENTS,
        );
        $deliverExecutionMock
            ->method('getId')
            ->willReturn('mode#userId#deliveryId#attemptId#tenant-id');
        $deliverExecutionMock
            ->expects($this->atLeastOnce())
            ->method('isStateFinal')
            ->willReturnCallback(fn() => $isClosedByService);
        $deliverExecutionMock
            ->expects($this->atLeastOnce())
            ->method('getFinishedAt')
            ->willReturn(Date::createFromDefaultFormat('2023-07-11T16:29:24.016+02:00'));
        $deliverExecutionMock->expects($this->once())->method('getTenantId')->willReturn(self::EXPECTED_TENANT_ID);
        $deliverExecutionMock->method('hasUiEvents')->willReturn(true);
        $deliverExecutionMock
            ->method('popAllUiEvents')
            ->willReturn($uiEventMessage);
        $deliverExecutionMock->method('hasAssessmentEvents')->willReturn(true);
        $deliverExecutionMock
            ->method('popAllAssessmentEvents')
            ->willReturn($assessmentEventMessages);

        $this->auditDeliveryExecutionLoggerMock
            ->expects(self::exactly(3))
            ->method('info')
            ->withConsecutive(
                ['[mode#userId#deliveryId#attemptId#tenant-id] Synchronizing DeliveryExecution'],
                [
                    sprintf(
                        '[mode#userId#deliveryId#attemptId#tenant-id] - received %u UI events',
                        count(self::UI_EVENTS),
                    ),
                ],
                [
                    sprintf(
                        '[mode#userId#deliveryId#attemptId#tenant-id] - received %u assessment-control events',
                        count($assessmentEventMessages),
                    ),
                ],
            );

        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->with(new DeliveryExecutionCreatedEvent($deliverExecutionMock));

        $this->qtiCompiledDeliveriesStorageMock
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $this->deliveryExecutionFactoryMock
            ->expects($this->once())
            ->method('createFromDeliveryExecutionDto')
            ->with($deliveryExecutionDto)
            ->willReturn($deliverExecutionMock);

        $this->deliveryExecutionClosureServiceMock
            ->expects($this->once())
            ->method('close')
            ->with($deliverExecutionMock)
            ->willReturnCallback(function (DeliveryExecution $deliveryExecution) use (&$isClosedByService) {
                $isClosedByService = true;
                return true;
            });

        $this->messageBusMock
            ->expects($this->exactly(count($assessmentEventMessages) + 1))
            ->method('dispatch')
            ->withConsecutive(
                ...[
                    [$uiEventMessage],
                    ...array_map(
                        static fn(NormalizedExecutionControlMessage $message): array => [$message],
                        $assessmentEventMessages,
                    ),
                ],
            )
            ->willReturnOnConsecutiveCalls(
                ...
                array_map([Envelope::class, 'wrap'], [$uiEventMessage, ...$assessmentEventMessages]),
            );

        $this->subject->synchronize($deliveryExecutionDto, self::EXPECTED_TENANT_ID);
    }

    public function testSynchronizedSuccessfullyFinishedDeliveryExecution(): void
    {
        $data = array_merge(
            self::EXPECTED_DEFAULT_DATA,
            [
                'status' => 'finished',
                'finishedAt' => '2023-07-11T16:29:24.016+02:00',
            ],
        );
        $deliveryExecutionDto = DeliveryExecutionDto::createFromArray($data);
        $deliverExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliverExecutionMock->expects($this->atLeastOnce())->method('isStateFinal')->willReturn(true);
        $deliverExecutionMock
            ->expects($this->atLeastOnce())
            ->method('getFinishedAt')
            ->willReturn(Date::createFromDefaultFormat('2023-07-11T16:29:24.016+02:00'));
        $deliverExecutionMock->expects($this->once())->method('getTenantId')->willReturn(self::EXPECTED_TENANT_ID);

        $this->auditDeliveryExecutionLoggerMock
            ->expects(self::exactly(3))
            ->method('info')
            ->withConsecutive(
                ['[mode#userId#deliveryId#attemptId#tenant-id] Synchronizing DeliveryExecution'],
                ['[mode#userId#deliveryId#attemptId#tenant-id] DeliveryExecution successfully stored'],
                ['[mode#userId#deliveryId#attemptId#tenant-id] DeliveryExecution `TestSessionEndEvent` triggered'],
            );

        $this->eventDispatcherMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [new DeliveryExecutionCreatedEvent($deliverExecutionMock)],
                [new TestSessionEndEvent(SynchronizeDeliveryExecutionService::class, $deliverExecutionMock)],
            );

        $this->qtiCompiledDeliveriesStorageMock
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $this->deliveryExecutionFactoryMock
            ->expects($this->once())
            ->method('createFromDeliveryExecutionDto')
            ->with($deliveryExecutionDto)
            ->willReturn($deliverExecutionMock);

        $this->deliveryExecutionClosureServiceMock
            ->expects(self::exactly(1))
            ->method('close')
            ->with($deliverExecutionMock)
            ->willReturn(false);

        $this->subject->synchronize($deliveryExecutionDto, self::EXPECTED_TENANT_ID);
    }

    public function testSynchronizedSuccessfullyFinishedDeliveryExecutionWithUiEvents(): void
    {
        $data = array_merge(
            self::EXPECTED_DEFAULT_DATA,
            [
                'status' => 'finished',
                'finishedAt' => '2023-07-11T16:29:24.016+02:00',
                'extraStateData' => ['uiEvents' => self::UI_EVENTS],
            ],
        );
        $deliveryExecutionDto = DeliveryExecutionDto::createFromArray($data);
        $deliverExecutionMock = $this->createMock(DeliveryExecution::class);
        $uiEventMessage = new DeliveryExecutionUIEventMessage($deliverExecutionMock, self::UI_EVENTS);
        $deliverExecutionMock
            ->method('getId')
            ->willReturn('mode#userId#deliveryId#attemptId#tenant-id');
        $deliverExecutionMock->expects($this->atLeastOnce())->method('isStateFinal')->willReturn(true);
        $deliverExecutionMock
            ->expects($this->atLeastOnce())
            ->method('getFinishedAt')
            ->willReturn(Date::createFromDefaultFormat('2023-07-11T16:29:24.016+02:00'));
        $deliverExecutionMock->expects($this->once())->method('getTenantId')->willReturn(self::EXPECTED_TENANT_ID);
        $deliverExecutionMock->method('hasUiEvents')->willReturn(true);
        $deliverExecutionMock
            ->method('popAllUiEvents')
            ->willReturn($uiEventMessage);

        $this->auditDeliveryExecutionLoggerMock
            ->expects(self::exactly(4))
            ->method('info')
            ->withConsecutive(
                ['[mode#userId#deliveryId#attemptId#tenant-id] Synchronizing DeliveryExecution'],
                [
                    sprintf(
                        '[mode#userId#deliveryId#attemptId#tenant-id] - received %u UI events',
                        count(self::UI_EVENTS),
                    ),
                ],
                ['[mode#userId#deliveryId#attemptId#tenant-id] DeliveryExecution successfully stored'],
                ['[mode#userId#deliveryId#attemptId#tenant-id] DeliveryExecution `TestSessionEndEvent` triggered'],
            );

        $this->eventDispatcherMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [new DeliveryExecutionCreatedEvent($deliverExecutionMock)],
                [new TestSessionEndEvent(SynchronizeDeliveryExecutionService::class, $deliverExecutionMock)],
            );

        $this->qtiCompiledDeliveriesStorageMock
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $this->deliveryExecutionFactoryMock
            ->expects($this->once())
            ->method('createFromDeliveryExecutionDto')
            ->with($deliveryExecutionDto)
            ->willReturn($deliverExecutionMock);

        $this->deliveryExecutionClosureServiceMock
            ->expects(self::exactly(1))
            ->method('close')
            ->with($deliverExecutionMock)
            ->willReturn(false);

        $this->messageBusMock
            ->expects($this->once())
            ->method('dispatch')
            ->with($uiEventMessage)
            ->willReturn(Envelope::wrap($uiEventMessage));

        $this->subject->synchronize($deliveryExecutionDto, self::EXPECTED_TENANT_ID);
    }

    public function testSynchronizedSuccessfullyFinishedDeliveryExecutionWithAssessmentEvents(): void
    {
        $data = array_merge(
            self::EXPECTED_DEFAULT_DATA,
            [
                'status' => 'finished',
                'finishedAt' => '2023-07-11T16:29:24.016+02:00',
                'extraStateData' => ['assessmentEvents' => self::ASSESSMENT_EVENTS],
            ],
        );
        $deliveryExecutionDto = DeliveryExecutionDto::createFromArray($data);
        $deliverExecutionMock = $this->createMock(DeliveryExecution::class);
        $assessmentEventMessages = array_map(
            static fn(
                array $normalizedAssessmentEventMessage,
            ): NormalizedExecutionControlMessage => new NormalizedExecutionControlMessage(
                $deliverExecutionMock,
                $normalizedAssessmentEventMessage,
            ),
            self::ASSESSMENT_EVENTS,
        );
        $deliverExecutionMock
            ->method('getId')
            ->willReturn('mode#userId#deliveryId#attemptId#tenant-id');
        $deliverExecutionMock->expects($this->atLeastOnce())->method('isStateFinal')->willReturn(true);
        $deliverExecutionMock
            ->expects($this->atLeastOnce())
            ->method('getFinishedAt')
            ->willReturn(Date::createFromDefaultFormat('2023-07-11T16:29:24.016+02:00'));
        $deliverExecutionMock->expects($this->once())->method('getTenantId')->willReturn(self::EXPECTED_TENANT_ID);
        $deliverExecutionMock->method('hasAssessmentEvents')->willReturn(true);
        $deliverExecutionMock
            ->method('popAllAssessmentEvents')
            ->willReturn($assessmentEventMessages);

        $this->auditDeliveryExecutionLoggerMock
            ->expects(self::exactly(4))
            ->method('info')
            ->withConsecutive(
                ['[mode#userId#deliveryId#attemptId#tenant-id] Synchronizing DeliveryExecution'],
                [
                    sprintf(
                        '[mode#userId#deliveryId#attemptId#tenant-id] - received %u assessment-control events',
                        count($assessmentEventMessages),
                    ),
                ],
                ['[mode#userId#deliveryId#attemptId#tenant-id] DeliveryExecution successfully stored'],
                ['[mode#userId#deliveryId#attemptId#tenant-id] DeliveryExecution `TestSessionEndEvent` triggered'],
            );

        $this->eventDispatcherMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [new DeliveryExecutionCreatedEvent($deliverExecutionMock)],
                [new TestSessionEndEvent(SynchronizeDeliveryExecutionService::class, $deliverExecutionMock)],
            );

        $this->qtiCompiledDeliveriesStorageMock
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $this->deliveryExecutionFactoryMock
            ->expects($this->once())
            ->method('createFromDeliveryExecutionDto')
            ->with($deliveryExecutionDto)
            ->willReturn($deliverExecutionMock);

        $this->deliveryExecutionClosureServiceMock
            ->expects(self::exactly(1))
            ->method('close')
            ->with($deliverExecutionMock)
            ->willReturn(false);

        $this->messageBusMock
            ->expects($this->exactly(count($assessmentEventMessages)))
            ->method('dispatch')
            ->withConsecutive(
                ...array_map(
                    static fn(NormalizedExecutionControlMessage $message): array => [$message],
                    $assessmentEventMessages,
                ),
            )
            ->willReturnOnConsecutiveCalls(...array_map([Envelope::class, 'wrap'], $assessmentEventMessages));

        $this->subject->synchronize($deliveryExecutionDto, self::EXPECTED_TENANT_ID);
    }

    public function testSynchronizedSuccessfullyFinishedDeliveryExecutionWithUiAndAssessmentEvents(): void
    {
        $data = array_merge(
            self::EXPECTED_DEFAULT_DATA,
            [
                'status' => 'finished',
                'finishedAt' => '2023-07-11T16:29:24.016+02:00',
                'extraStateData' => ['uiEvents' => self::UI_EVENTS, 'assessmentEvents' => self::ASSESSMENT_EVENTS],
            ],
        );
        $deliveryExecutionDto = DeliveryExecutionDto::createFromArray($data);
        $deliverExecutionMock = $this->createMock(DeliveryExecution::class);
        $uiEventMessage = new DeliveryExecutionUIEventMessage($deliverExecutionMock, self::UI_EVENTS);
        $assessmentEventMessages = array_map(
            static fn(
                array $normalizedAssessmentEventMessage,
            ): NormalizedExecutionControlMessage => new NormalizedExecutionControlMessage(
                $deliverExecutionMock,
                $normalizedAssessmentEventMessage,
            ),
            self::ASSESSMENT_EVENTS,
        );
        $deliverExecutionMock
            ->method('getId')
            ->willReturn('mode#userId#deliveryId#attemptId#tenant-id');
        $deliverExecutionMock->expects($this->atLeastOnce())->method('isStateFinal')->willReturn(true);
        $deliverExecutionMock
            ->expects($this->atLeastOnce())
            ->method('getFinishedAt')
            ->willReturn(Date::createFromDefaultFormat('2023-07-11T16:29:24.016+02:00'));
        $deliverExecutionMock->expects($this->once())->method('getTenantId')->willReturn(self::EXPECTED_TENANT_ID);
        $deliverExecutionMock->method('hasUiEvents')->willReturn(true);
        $deliverExecutionMock
            ->method('popAllUiEvents')
            ->willReturn($uiEventMessage);
        $deliverExecutionMock->method('hasAssessmentEvents')->willReturn(true);
        $deliverExecutionMock
            ->method('popAllAssessmentEvents')
            ->willReturn($assessmentEventMessages);

        $this->auditDeliveryExecutionLoggerMock
            ->expects(self::exactly(5))
            ->method('info')
            ->withConsecutive(
                ['[mode#userId#deliveryId#attemptId#tenant-id] Synchronizing DeliveryExecution'],
                [
                    sprintf(
                        '[mode#userId#deliveryId#attemptId#tenant-id] - received %u UI events',
                        count(self::UI_EVENTS),
                    ),
                ],
                [
                    sprintf(
                        '[mode#userId#deliveryId#attemptId#tenant-id] - received %u assessment-control events',
                        count($assessmentEventMessages),
                    ),
                ],
                ['[mode#userId#deliveryId#attemptId#tenant-id] DeliveryExecution successfully stored'],
                ['[mode#userId#deliveryId#attemptId#tenant-id] DeliveryExecution `TestSessionEndEvent` triggered'],
            );

        $this->eventDispatcherMock
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [new DeliveryExecutionCreatedEvent($deliverExecutionMock)],
                [new TestSessionEndEvent(SynchronizeDeliveryExecutionService::class, $deliverExecutionMock)],
            );

        $this->qtiCompiledDeliveriesStorageMock
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $this->deliveryExecutionFactoryMock
            ->expects($this->once())
            ->method('createFromDeliveryExecutionDto')
            ->with($deliveryExecutionDto)
            ->willReturn($deliverExecutionMock);

        $this->deliveryExecutionClosureServiceMock
            ->expects($this->once())
            ->method('close')
            ->with($deliverExecutionMock)
            ->willReturn(false);

        $this->messageBusMock
            ->expects($this->exactly(count($assessmentEventMessages) + 1))
            ->method('dispatch')
            ->withConsecutive(
                ...[
                    [$uiEventMessage],
                    ...array_map(
                        static fn(NormalizedExecutionControlMessage $message): array => [$message],
                        $assessmentEventMessages,
                    ),
                ],
            )
            ->willReturnOnConsecutiveCalls(
                ...
                array_map([Envelope::class, 'wrap'], [$uiEventMessage, ...$assessmentEventMessages]),
            );

        $this->subject->synchronize($deliveryExecutionDto, self::EXPECTED_TENANT_ID);
    }

    public function testCreateFailedFinishedDeliveryExecutionWithoutFinishedAt(): void
    {
        $data = array_merge(
            self::EXPECTED_DEFAULT_DATA,
            [
                'status' => 'finished',
            ],
        );
        $deliveryExecutionDto = DeliveryExecutionDto::createFromArray($data);
        $deliverExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliverExecutionMock->expects($this->atLeastOnce())->method('isStateFinal')->willReturn(true);
        $deliverExecutionMock
            ->expects($this->atLeastOnce())
            ->method('getFinishedAt')
            ->willReturn(null);
        $deliverExecutionMock->expects($this->once())->method('getTenantId')->willReturn(self::EXPECTED_TENANT_ID);
        $deliverExecutionMock->expects(self::once())->method('getId')->willReturn(
            'mode#userId#deliveryId#attemptId#tenant-id',
        );

        $this->qtiCompiledDeliveriesStorageMock
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $this->auditDeliveryExecutionLoggerMock
            ->expects(self::exactly(1))
            ->method('info')
            ->withConsecutive(
                ['[mode#userId#deliveryId#attemptId#tenant-id] Synchronizing DeliveryExecution'],
            );

        $this->eventDispatcherMock->expects($this->once())->method('dispatch');
        $this->deliveryExecutionFactoryMock
            ->expects($this->once())
            ->method('createFromDeliveryExecutionDto')
            ->with($deliveryExecutionDto)
            ->willReturn($deliverExecutionMock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            '[mode#userId#deliveryId#attemptId#tenant-id] DeliveryExecution is final but has no end date',
        );
        $this->subject->synchronize($deliveryExecutionDto, self::EXPECTED_TENANT_ID);
    }

    public function testCreateFailedWithNotExistedDelivery(): void
    {
        $data = array_merge(
            self::EXPECTED_DEFAULT_DATA,
            [
                'status' => 'finished',
                'finishedAt' => '2023-07-11T16:29:24.016+02:00',
            ],
        );
        $deliveryExecutionDto = DeliveryExecutionDto::createFromArray($data);
        $deliverExecutionMock = $this->createMock(DeliveryExecution::class);
        $deliverExecutionMock->expects($this->never())->method('isStateFinal')->willReturn(true);
        $deliverExecutionMock
            ->expects($this->never())
            ->method('getFinishedAt')
            ->willReturn(null);
        $deliverExecutionMock->expects($this->once())->method('getTenantId')->willReturn(self::EXPECTED_TENANT_ID);

        $this->qtiCompiledDeliveriesStorageMock
            ->expects($this->once())
            ->method('has')
            ->willReturn(false);

        $this->auditDeliveryExecutionLoggerMock
            ->expects(self::exactly(1))
            ->method('info')
            ->withConsecutive(
                ['[mode#userId#deliveryId#attemptId#tenant-id] Synchronizing DeliveryExecution'],
            );

        $this->eventDispatcherMock->expects($this->never())->method('dispatch');
        $this->deliveryExecutionFactoryMock
            ->expects($this->once())
            ->method('createFromDeliveryExecutionDto')
            ->with($deliveryExecutionDto)
            ->willReturn($deliverExecutionMock);

        $this->expectException(InvalidArgumentException::class);
        $this->subject->synchronize($deliveryExecutionDto, self::EXPECTED_TENANT_ID);
    }
}
