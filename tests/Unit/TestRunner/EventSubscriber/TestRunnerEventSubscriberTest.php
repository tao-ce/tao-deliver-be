<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\EventSubscriber;

use App\DataStore\Sender\DataStoreSenderInterface;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Generator\UuidGenerator;
use App\Lti\LtiCustomSettings;
use App\Messenger\Message\DeliveryExecution\DeliveryExecutionFinishedMessage;
use App\Messenger\Message\InteractionMessage;
use App\Messenger\Message\ResultExtractionMessage;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\ActionProcessor\MoveActionProcessor;
use App\TestRunner\Event\ProctoredDeliveryExecutionInitializedEvent;
use App\TestRunner\Event\TestSessionEndEvent;
use App\TestRunner\Event\TestSessionInteractionEvent;
use App\TestRunner\EventSubscriber\TestRunnerEventSubscriber;
use App\TestRunner\Generator\TestMapGenerator;
use App\TestRunner\Service\InteractionMessageService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\LoggerTestingTrait;
use App\Tests\Traits\MessengerTestingTrait;
use App\Tests\Traits\QtiTestingTrait;
use Carbon\Carbon;
use DateInterval;
use App\Messenger\Stamp\MetadataStamp;
use Monolog\Logger;
use OAT\Library\Lti1p3Core\Exception\LtiException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use qtism\runtime\tests\AssessmentTestSession;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

class TestRunnerEventSubscriberTest extends KernelTestCase
{
    use LoggerTestingTrait;
    use MessengerTestingTrait;
    use DomainTestingTrait;
    use QtiTestingTrait;

    private DeliveryExecutionPropertyService $deliveryExecutionPropertyService;
    private TestRunnerEventSubscriber $subject;
    private DataStoreSenderInterface $dataStoreSenderMock;
    private MessageBusInterface $messageBusMock;
    private RequestStack $requestStackMock;
    private DeliveryExecutionServiceInterface $deliveryExecutionServiceMock;

    protected function setUp(): void
    {
        self::bootKernel();

        Carbon::setTestNow(Carbon::createFromTimestamp(1597248430));

        $this->setUpTestLogHandler();
        $this->setUpTestMessageBus();

        $this->deliveryExecutionPropertyService = static::getContainer()->get(DeliveryExecutionPropertyService::class);
        $this->dataStoreSenderMock = $this->createMock(DataStoreSenderInterface::class);
        $this->messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->requestStackMock = $this->createMock(RequestStack::class);
        $this->deliveryExecutionServiceMock = $this->createMock(DeliveryExecutionServiceInterface::class);

        $this->subject = new TestRunnerEventSubscriber(
            new UuidGenerator(),
            $this->messageBusMock,
            static::getContainer()->get(EventDispatcherInterface::class),
            static::getContainer()->get(LoggerInterface::class),
            $this->deliveryExecutionServiceMock,
            static::getContainer()->get(LtiCustomSettings::class),
            $this->dataStoreSenderMock,
            new InteractionMessageService(
                $this->deliveryExecutionPropertyService,
                static::getContainer()->get(LoggerInterface::class),
                $this->messageBusMock,
                $this->requestStackMock,
                static::getContainer()->get(TestMapGenerator::class),
            ),
        );
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function testOnTestSessionInteraction(): void
    {
        /** @var DeliveryExecution $deliveryExecution */
        /** @var AssessmentTestSession $testSession */
        [$deliveryExecution, $testSession] = $this->initiateTestSession();

        $event = new TestSessionInteractionEvent(
            MoveActionProcessor::class,
            MoveActionProcessor::ACTION_NAME,
            $deliveryExecution,
            $testSession,
        );

        $expectedMessage = new InteractionMessage(
            deliveryExecutionId: 'userId#Basic#resultId#tenantId',
            deliveryId: 'Basic',
            tenantId: 'tenantId',
            deliveryExecutionStartedAt: 1597248430,
            durationInSeconds: 0,
            ipAddress: '98.76.54.231',
            position: [
                'item' => 1,
                'informationalIndex' => 0,
                'total' => 3,
            ],
            progressPercentage: 0,
            title: 'Basic Test (Linear-Individual)',
            questions: 3,
            questionsViewed: 1,
            answered: 0,
            flagged: 0,
            viewed: 1,
            deliveryExecutionFinishedAt: 1597248430,
            deliveryExecutionStatus: 'initial',
            positionDetails: [
                'item' => [
                    'id' => 'Item-Q01',
                ],
                'section' => [
                    'id' => 'Section-S01',
                ],
                'part' => [
                    'id' => 'TestPart-TP01',
                ],
            ],
        );

        $request = new Request([], [], [], [], [], ['REMOTE_ADDR' => '127.0.0.6', 'HTTP_X_FORWARDED_FOR' => '98.76.54.231,34.149.23.32,130.211.1.171']);
        $request::setTrustedProxies([getenv('TRUSTED_PROXIES')], Request::HEADER_X_FORWARDED_FOR);

        $this->requestStackMock->method('getCurrentRequest')
            ->willReturn($request);

        $expectedStamps = [
            new MetadataStamp(
                $deliveryExecution->getLtiLaunchParameters()['context_id'] ?? 'default-context-id',
            ),
        ];

        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->with($expectedMessage, $expectedStamps);

        $this->subject->onTestSessionInteraction($event);
    }

    public function testOnMultiPartInformationalItemTestSessionInteraction(): void
    {
        /** @var DeliveryExecution $deliveryExecution */
        /** @var AssessmentTestSession $testSession */
        [$deliveryExecution, $testSession] = $this->initiateMultiPartInformationalItemTestSession();

        $event = new TestSessionInteractionEvent(
            MoveActionProcessor::class,
            MoveActionProcessor::ACTION_NAME,
            $deliveryExecution,
            $testSession,
        );

        $expectedMessage = new InteractionMessage(
            deliveryExecutionId: 'userId#MultiPartInformationalItems#resultId#tenantId',
            deliveryId: 'MultiPartInformationalItems',
            tenantId: 'tenantId',
            deliveryExecutionStartedAt: 1597248430,
            durationInSeconds: 0,
            ipAddress: null,
            position: [
                'part' => 1,
                'item' => 1,
                'informationalIndex' => 0,
                'total' => 4,
            ],
            progressPercentage: 0,
            title: 'Multi-part Test (Linear-Individual)',
            questions: 4,
            questionsViewed: 1,
            answered: 0,
            flagged: 0,
            viewed: 1,
            deliveryExecutionFinishedAt: 1597248430,
            deliveryExecutionStatus: 'initial',
            positionDetails: [
                'item' => [
                    'id' => 'Item-P01-S01-Q01',
                ],
                'section' => [
                    'id' => 'Section-P01-S01',
                ],
                'part' => [
                    'id' => 'TestPart-TP01',
                ],
            ],
        );

        $expectedStamps = [
            new MetadataStamp(
                $deliveryExecution->getLtiLaunchParameters()['context_id'] ?? 'default-context-id',
            ),
        ];
        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->with($expectedMessage, $expectedStamps);

        $this->subject->onTestSessionInteraction($event);
    }

    public function testOnSecondSectionOfMultiPartInformationalItemTestSessionInteraction(): void
    {
        /** @var DeliveryExecution $deliveryExecution */
        /** @var AssessmentTestSession $testSession */
        [$deliveryExecution, $testSession] = $this->initiateMultiPartInformationalItemTestSession();

        // go to the second section of test
        for ($i = 0; $i < 3; $i++) {
            $testSession->moveNext();
        }

        $event = new TestSessionInteractionEvent(
            MoveActionProcessor::class,
            MoveActionProcessor::ACTION_NAME,
            $deliveryExecution,
            $testSession,
        );

        $expectedMessage = new InteractionMessage(
            deliveryExecutionId: 'userId#MultiPartInformationalItems#resultId#tenantId',
            deliveryId: 'MultiPartInformationalItems',
            tenantId: 'tenantId',
            deliveryExecutionStartedAt: 1597248430,
            durationInSeconds: 0,
            ipAddress: null,
            position: [
                'part' => 1,
                'item' => 0,
                'informationalIndex' => 2,
                'total' => 4,
            ],
            progressPercentage: 0,
            title: 'Multi-part Test (Linear-Individual)',
            questions: 4,
            questionsViewed: 1,
            answered: 0,
            flagged: 0,
            viewed: 1,
            deliveryExecutionFinishedAt: 1597248430,
            deliveryExecutionStatus: 'initial',
            positionDetails: [
                'item' => [
                    'id' => 'Item-P01-S02-Q01',
                ],
                'section' => [
                    'id' => 'Section-P01-S02',
                ],
                'part' => [
                    'id' => 'TestPart-TP01',
                ],
            ],
        );

        $expectedStamps = [
            new MetadataStamp(
                $deliveryExecution->getLtiLaunchParameters()['context_id'] ?? 'default-context-id',
            ),
        ];

        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->with($expectedMessage, $expectedStamps);

        $this->subject->onTestSessionInteraction($event);
    }

    public function testOnSecondPartOfMultiPartInformationalItemTestSessionInteraction(): void
    {
        /** @var DeliveryExecution $deliveryExecution */
        /** @var AssessmentTestSession $testSession */
        [$deliveryExecution, $testSession] = $this->initiateMultiPartInformationalItemTestSession();

        // go to the second section of test
        for ($i = 0; $i < 6; $i++) {
            $testSession->moveNext();
        }

        $event = new TestSessionInteractionEvent(
            MoveActionProcessor::class,
            MoveActionProcessor::ACTION_NAME,
            $deliveryExecution,
            $testSession,
        );

        $expectedMessage = new InteractionMessage(
            deliveryExecutionId: 'userId#MultiPartInformationalItems#resultId#tenantId',
            deliveryId: 'MultiPartInformationalItems',
            tenantId: 'tenantId',
            deliveryExecutionStartedAt: 1597248430,
            durationInSeconds: 0,
            ipAddress: null,
            position: [
                'part' => 2,
                'item' => 4,
                'informationalIndex' => 0,
                'total' => 4,
            ],
            progressPercentage: 0,
            title: 'Multi-part Test (Linear-Individual)',
            questions: 4,
            questionsViewed: 1,
            answered: 0,
            flagged: 0,
            viewed: 1,
            deliveryExecutionFinishedAt: 1597248430,
            deliveryExecutionStatus: 'initial',
            positionDetails: [
                'item' => [
                    'id' => 'Item-P02-S01-Q01',
                ],
                'section' => [
                    'id' => 'Section-P02-S01',
                ],
                'part' => [
                    'id' => 'TestPart-TP02',
                ],
            ],
        );

        $expectedStamps = [
            new MetadataStamp(
                $deliveryExecution->getLtiLaunchParameters()['context_id'] ?? 'default-context-id',
            ),
        ];
        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->with($expectedMessage, $expectedStamps);

        $this->subject->onTestSessionInteraction($event);
    }

    public function testOnMultiPartTestSessionInteraction(): void
    {
        /** @var DeliveryExecution $deliveryExecution */
        /** @var AssessmentTestSession $testSession */
        [$deliveryExecution, $testSession] = $this->initiateMultiPartTestSession();

        $event = new TestSessionInteractionEvent(
            MoveActionProcessor::class,
            MoveActionProcessor::ACTION_NAME,
            $deliveryExecution,
            $testSession,
        );

        $expectedMessage = new InteractionMessage(
            deliveryExecutionId: 'userId#MultiPart#resultId#tenantId',
            deliveryId: 'MultiPart',
            tenantId: 'tenantId',
            deliveryExecutionStartedAt: 1597248430,
            durationInSeconds: 0,
            ipAddress: null,
            position: [
                'part' => 1,
                'item' => 1,
                'informationalIndex' => 0,
                'total' => 9,
            ],
            progressPercentage: 0,
            title: 'Multi-part Test (Linear-Individual)',
            questions: 9,
            questionsViewed: 1,
            answered: 0,
            flagged: 0,
            viewed: 1,
            deliveryExecutionFinishedAt: 1597248430,
            deliveryExecutionStatus: 'initial',
            positionDetails: [
                'item' => [
                    'id' => 'Item-P01-S01-Q01',
                ],
                'section' => [
                    'id' => 'Section-P01-S01',
                ],
                'part' => [
                    'id' => 'TestPart-TP01',
                ],
            ],
        );

        $expectedStamps = [
            new MetadataStamp(
                $deliveryExecution->getLtiLaunchParameters()['context_id'] ?? 'default-context-id',
            ),
        ];
        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->with($expectedMessage, $expectedStamps);

        $this->subject->onTestSessionInteraction($event);
    }

    public function testOnSecondSectionOfMultiPartTestSessionInteraction(): void
    {
        /** @var DeliveryExecution $deliveryExecution */
        /** @var AssessmentTestSession $testSession */
        [$deliveryExecution, $testSession] = $this->initiateMultiPartTestSession();

        // go to the second section of test
        for ($i = 0; $i < 3; $i++) {
            $testSession->moveNext();
        }

        $event = new TestSessionInteractionEvent(
            MoveActionProcessor::class,
            MoveActionProcessor::ACTION_NAME,
            $deliveryExecution,
            $testSession,
        );

        $expectedMessage = new InteractionMessage(
            deliveryExecutionId: 'userId#MultiPart#resultId#tenantId',
            deliveryId: 'MultiPart',
            tenantId: 'tenantId',
            deliveryExecutionStartedAt: 1597248430,
            durationInSeconds: 0,
            ipAddress: null,
            position: [
                'part' => 1,
                'item' => 4,
                'informationalIndex' => 0,
                'total' => 9,
            ],
            progressPercentage: 0,
            title: 'Multi-part Test (Linear-Individual)',
            questions: 9,
            questionsViewed: 1,
            answered: 0,
            flagged: 0,
            viewed: 1,
            deliveryExecutionFinishedAt: 1597248430,
            deliveryExecutionStatus: 'initial',
            positionDetails: [
                'item' => [
                    'id' => 'Item-P01-S02-Q01',
                ],
                'section' => [
                    'id' => 'Section-P01-S02',
                ],
                'part' => [
                    'id' => 'TestPart-TP01',
                ],
            ],
        );

        $expectedStamps = [
            new MetadataStamp(
                $deliveryExecution->getLtiLaunchParameters()['context_id'] ?? 'default-context-id',
            ),
        ];
        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->with($expectedMessage, $expectedStamps);

        $this->subject->onTestSessionInteraction($event);
    }

    public function testOnSecondPartOfMultiPartTestSessionInteraction(): void
    {
        /** @var DeliveryExecution $deliveryExecution */
        /** @var AssessmentTestSession $testSession */
        [$deliveryExecution, $testSession] = $this->initiateMultiPartTestSession();

        // go to the second section of test
        for ($i = 0; $i < 6; $i++) {
            $testSession->moveNext();
        }

        $event = new TestSessionInteractionEvent(
            MoveActionProcessor::class,
            MoveActionProcessor::ACTION_NAME,
            $deliveryExecution,
            $testSession,
        );

        $expectedMessage = new InteractionMessage(
            deliveryExecutionId: 'userId#MultiPart#resultId#tenantId',
            deliveryId: 'MultiPart',
            tenantId: 'tenantId',
            deliveryExecutionStartedAt: 1597248430,
            durationInSeconds: 0,
            ipAddress: null,
            position: [
                'part' => 2,
                'item' => 7,
                'informationalIndex' => 0,
                'total' => 9,
            ],
            progressPercentage: 0,
            title: 'Multi-part Test (Linear-Individual)',
            questions: 9,
            questionsViewed: 1,
            answered: 0,
            flagged: 0,
            viewed: 1,
            deliveryExecutionFinishedAt: 1597248430,
            deliveryExecutionStatus: 'initial',
            positionDetails: [
                'item' => [
                    'id' => 'Item-P02-S01-Q01',
                ],
                'section' => [
                    'id' => 'Section-P02-S01',
                ],
                'part' => [
                    'id' => 'TestPart-TP02',
                ],
            ],
        );

        $expectedStamps = [
            new MetadataStamp(
                $deliveryExecution->getLtiLaunchParameters()['context_id'] ?? 'default-context-id',
            ),
        ];
        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->with($expectedMessage, $expectedStamps);

        $this->subject->onTestSessionInteraction($event);
    }

    public function testOnTestSessionInteractionWhenTestSessionFinished(): void
    {
        /** @var DeliveryExecution $deliveryExecution */
        /** @var AssessmentTestSession $testSession */
        [$deliveryExecution, $testSession] = $this->initiateTestSession();

        // go to the end of test
        $testSession->moveNext();
        $testSession->moveNext();
        $testSession->moveNext();

        $finished = 1597248430;
        $started = $finished - 3600;
        $testSession['duration']->add(new DateInterval('PT1H'));

        $deliveryExecution
            ->setStatus(DeliveryExecution::STATUS_CLOSED)
            ->setFinishedAt(Carbon::now());

        $event = new TestSessionInteractionEvent(
            MoveActionProcessor::class,
            MoveActionProcessor::ACTION_NAME,
            $deliveryExecution,
            $testSession,
        );

        $expectedMessage = new InteractionMessage(
            deliveryExecutionId: 'userId#Basic#resultId#tenantId',
            deliveryId: 'Basic',
            tenantId: 'tenantId',
            deliveryExecutionStartedAt: $started,
            durationInSeconds: 3600,
            ipAddress: null,
            position: [
                'item' => 3,
                'informationalIndex' => 0,
                'total' => 3,
            ],
            progressPercentage: 0,
            title: 'Basic Test (Linear-Individual)',
            questions: 3,
            questionsViewed: 1,
            answered: 0,
            flagged: 0,
            viewed: 1,
            deliveryExecutionFinishedAt: $finished,
            deliveryExecutionStatus: 'closed',
            positionDetails: [
                'item' => [
                    'id' => 'Item-Q03',
                ],
                'section' => [
                    'id' => 'Section-S01',
                ],
                'part' => [
                    'id' => 'TestPart-TP01',
                ],
            ],
        );

        $expectedStamps = [
            new MetadataStamp(
                $deliveryExecution->getLtiLaunchParameters()['context_id'] ?? 'default-context-id',
            ),
        ];
        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->with($expectedMessage, $expectedStamps);

        $this->subject->onTestSessionInteraction($event);
    }

    public function testOnProctoringAuthorizationRequestSend(): void
    {
        $this->copyCompiledTestToStorage(
            [
                'compact-test.xml',
                'Item-Q01/item.json',
                'Item-Q02/item.json',
                'Item-Q03/item.json',
            ],
        );

        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            ['custom' => [LtiCustomSettings::PARAM_ENABLE_MONITORING => true]],
            null,
        );

        $deliveryExecution->setLtiLaunchParameters(
            array_merge(
                $deliveryExecution->getLtiLaunchParameters(),
                ['context_id' => 'test'],
            ),
        );

        $event = new ProctoredDeliveryExecutionInitializedEvent('trigger', $deliveryExecution);

        $expectedMessage = new InteractionMessage(
            deliveryExecutionId: 'userId#Basic#resultId#tenantId',
            deliveryId: 'Basic',
            tenantId: 'tenantId',
            deliveryExecutionStartedAt: 1597248430,
            durationInSeconds: 0,
            ipAddress: null,
            position: [
                'item' => 1,
                'informationalIndex' => 0,
                'total' => 3,
            ],
            title: 'Basic Test (Linear-Individual)',
            deliveryExecutionFinishedAt: 1597248430,
            deliveryExecutionStatus: DeliveryExecution::STATUS_INITIAL,
            positionDetails: [
                'item' => [
                    'id' => 'Item-Q01',
                ],
                'section' => [
                    'id' => 'Section-S01',
                ],
                'part' => [
                    'id' => 'TestPart-TP01',
                ],
            ],
        );

        $expectedStamps = [
            new MetadataStamp(
                $deliveryExecution->getLtiLaunchParameters()['context_id'] ?? 'default-context-id',
            ),
        ];
        $this->messageBusMock
            ->expects(self::once())
            ->method('dispatch')
            ->with($expectedMessage, $expectedStamps);

        $this->subject->onProctoringAuthorizationRequestSend($event);
    }

    public function testOnProctoringAuthorizationRequestRequireContextIdClaim(): void
    {
        /** @var DeliveryExecution $deliveryExecution */
        /** @var AssessmentTestSession $testSession */
        [$deliveryExecution, $testSession] = $this->initiateTestSession();

        // go to the end of test
        $testSession->moveNext();
        $testSession->moveNext();
        $testSession->moveNext();

        $deliveryExecution
            ->setStatus(DeliveryExecution::STATUS_INITIAL);

        $event = new ProctoredDeliveryExecutionInitializedEvent('trigger', $deliveryExecution);

        $this->expectException(LtiException::class);
        $this->expectExceptionMessage('[userId#Basic#resultId#tenantId] Context::id claim is required for proctoring communication');

        $this->subject->onProctoringAuthorizationRequestSend($event);
    }

    public function testOnProctoringAuthorizationRequestSendSkip(): void
    {
        /** @var DeliveryExecution $deliveryExecution */
        /** @var AssessmentTestSession $testSession */
        [$deliveryExecution, $testSession] = $this->initiateTestSession();

        // go to the end of test
        $testSession->moveNext();
        $testSession->moveNext();
        $testSession->moveNext();

        $deliveryExecution
            ->setStatus(DeliveryExecution::STATUS_INTERACTING);

        $event = new ProctoredDeliveryExecutionInitializedEvent('trigger', $deliveryExecution);

        $this->messageBusMock
            ->expects(self::never())
            ->method('dispatch');

        $this->subject->onProctoringAuthorizationRequestSend($event);

        $this->assertHasLogRecordWithMessage(
            '[userId#Basic#resultId#tenantId] Skip Published interaction message, triggered by trigger: incorrect status',
            Logger::INFO,
        );
    }


    public function testOnTestSessionInteractionWhenMonitoringIsDisabledThenInteractionMessageNotSent(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#deliveryId#resultId#tenantId',
            'deliveryId',
            'tenantId',
            ['custom' => []],
            null,
        );

        $event = new TestSessionInteractionEvent(
            MoveActionProcessor::class,
            MoveActionProcessor::ACTION_NAME,
            $deliveryExecution,
            $this->createMock(AssessmentTestSession::class),
        );

        $this->messageBusMock
            ->expects(self::never())
            ->method('dispatch');

        $this->subject->onTestSessionInteraction($event);
    }

    public function testOnTestSessionEnded(): void
    {
        $event = new TestSessionEndEvent('trigger', $this->createTestDeliveryExecution());

        $subject = new TestRunnerEventSubscriber(
            new UuidGenerator(),
            static::getContainer()->get(MessageBusInterface::class),
            static::getContainer()->get(EventDispatcherInterface::class),
            static::getContainer()->get(LoggerInterface::class),
            $this->deliveryExecutionServiceMock,
            static::getContainer()->get(LtiCustomSettings::class),
            $this->dataStoreSenderMock,
            new InteractionMessageService(
                $this->deliveryExecutionPropertyService,
                static::getContainer()->get(LoggerInterface::class),
                $this->messageBusMock,
                $this->requestStackMock,
                static::getContainer()->get(TestMapGenerator::class),
            ),
        );

        $subject->onTestSessionEnd($event);

        $this->assertCountTransportMessages('result-extraction', 1);
        $this->assertHasTransportMessage('result-extraction', ResultExtractionMessage::class);

        $this->assertCountTransportMessages('delivery-execution', 1);
        $this->assertHasTransportMessage('delivery-execution', DeliveryExecutionFinishedMessage::class);

        $this->assertHasLogRecordWithMessage(
            '[userId#deliveryId#resultId#tenantId] Published result extraction message, triggered by trigger',
            Logger::DEBUG,
        );
    }

    private function initiateTestSession(): array
    {
        $this->copyCompiledTestToStorage(
            [
                'compact-test.xml',
                'Item-Q01/item.json',
                'Item-Q02/item.json',
                'Item-Q03/item.json',
            ],
        );

        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#Basic#resultId#tenantId',
            'Basic',
            'tenantId',
            ['custom' => [LtiCustomSettings::PARAM_ENABLE_MONITORING => true]],
            null,
        );

        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        $testSession->beginTestSession();
        $testSession->beginAttempt();

        return [$deliveryExecution, $testSession];
    }

    private function initiateMultiPartInformationalItemTestSession(): array
    {
        $this->copyCompiledTestToStorage(
            [
                'compact-test.xml',
                'Item-I01/item.json',
                'Item-Q01/item.json',
            ],
            'MultiPartInformationalItems',
        );

        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#MultiPartInformationalItems#resultId#tenantId',
            'MultiPartInformationalItems',
            'tenantId',
            ['custom' => [LtiCustomSettings::PARAM_ENABLE_MONITORING => true]],
            null,
        );

        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        $testSession->beginTestSession();
        $testSession->beginAttempt();

        return [$deliveryExecution, $testSession];
    }

    private function initiateMultiPartTestSession(): array
    {
        $this->copyCompiledTestToStorage(
            [
                'compact-test.xml',
                'Item-Q01/item.json',
                'Item-Q02/item.json',
                'Item-Q03/item.json',
            ],
            'MultiPart',
        );

        $deliveryExecution = $this->createTestDeliveryExecution(
            'userId#MultiPart#resultId#tenantId',
            'MultiPart',
            'tenantId',
            ['custom' => [LtiCustomSettings::PARAM_ENABLE_MONITORING => true]],
            null,
            null,
        );

        $testSession = $this->deliveryExecutionPropertyService->fetchTestSession($deliveryExecution);

        $testSession->beginTestSession();
        $testSession->beginAttempt();

        return [$deliveryExecution, $testSession];
    }
}
