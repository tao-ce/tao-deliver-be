<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Middleware;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorIdentity;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorRole;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionControlAction;
use App\Domain\Tenant\Model\DeliverProvisionedEventsSettingsRepositoryInterface;
use App\Messenger\Message\DeliveryExecution\ExecutionControlMessage;
use App\Messenger\Message\DeliveryExecution\ExecutionLogMessage;
use App\Messenger\Message\DeliveryExecutionAcsLogMessage;
use App\Messenger\Middleware\AssessmentLogMessageFilterMiddleware;
use App\TestRunner\Event\Control\ControlStatus;
use App\TestRunner\Event\Control\ControlType;
use Carbon\Carbon;
use OAT\Library\Lti1p3Proctoring\Model\AcsControlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackInterface;
use stdClass;

class AssessmentLogMessageFilterMiddlewareTest extends TestCase
{
    private DeliverProvisionedEventsSettingsRepositoryInterface&MockObject $deliverProvisionedEventsSettingsRepository;
    private StackInterface&MockObject $stack;
    private DeliveryExecution&MockObject $deliveryExecution;
    private array $messageMock;
    private AssessmentLogMessageFilterMiddleware $subject;

    protected function setUp(): void
    {
        $this->deliverProvisionedEventsSettingsRepository = $this->createMock(
            DeliverProvisionedEventsSettingsRepositoryInterface::class,
        );
        $this->stack = $this->createMock(StackInterface::class);

        $this->deliveryExecution = $this->createMock(DeliveryExecution::class);
        $this->deliveryExecution
            ->method('getId')
            ->willReturn('userId#deliveryId#resultId#tenantId');

        $this->messageMock = $this->createMessageMock();

        $this->subject = new AssessmentLogMessageFilterMiddleware(
            $this->deliverProvisionedEventsSettingsRepository,
        );
    }

    /**
     * @dataProvider messageTypeProvider
     */
    public function testMessagesBehaviorByConfiguration(array $configEvents, bool $isNextCalled, $messageTypes): void
    {
        $this->mockProvisionedEventsSettings($configEvents);
        $this->stack
            ->expects(
                $isNextCalled
                    ? $this->atLeast(count($messageTypes))
                    : $this->never(),
            )
            ->method('next');

        foreach ($messageTypes as $type) {
            $envelope = new Envelope($this->messageMock[$type]);
            $this->subject->handle($envelope, $this->stack);
        }
    }

    public static function messageTypeProvider(): array
    {
        return [
            'Event not part of configuration' => [
                'configEvents' => ['navigation'],
                'isNextCalled' => false,
                'messageTypes' => [
                    'proctorActions',
                    'systemActions',
                    'testTakerActions',
                    'resetAction',
                ],
            ],
            'Configuration allow all events' => [
                'configEvents' => ['*'],
                'isNextCalled' => true,
                'messageTypes' => [
                    'proctorActions',
                    'systemActions',
                    'testTakerActions',
                    'resetAction',
                ],
            ],
            'Configuration allow only existing event' => [
                'configEvents' => ['submission', 'pause', 'reset'],
                'isNextCalled' => true,
                'messageTypes' => [
                    'proctorActions',
                    'systemActions',
                    'testTakerActions',
                    'resetAction',
                ],
            ],
            'Other message ignore that configuration' => [
                'configEvents' => [],
                'isNextCalled' => true,
                'messageTypes' => [
                    'extraMessage',
                ],
            ],
        ];
    }

    private function createMessageMock(): array
    {
        $actorIdentity = new DeliveryExecutionActorIdentity(
            'test-taker',
            'Test Taker',
            DeliveryExecutionActorRole::ROLE_TEST_TAKER,
            null,
            null,
        );
        $action = $this->createMock(DeliveryExecutionControlAction::class);
        $acsControl = $this->createMock(AcsControlInterface::class);
        $acsControl
            ->method('getAction')
            ->willReturn('pause');

        $action
            ->method('getControlType')
            ->willReturn(ControlType::PAUSE);

        return [
            'testTakerActions' => new ExecutionControlMessage(
                $actorIdentity,
                $action,
                Carbon::now(),
                $this->deliveryExecution,
                null,
                null,
                null,
            ),
            'systemActions' => new ExecutionControlMessage(
                new DeliveryExecutionActorIdentity(
                    'system',
                    'TAO',
                    DeliveryExecutionActorRole::ROLE_SYSTEM,
                    null,
                    null,
                ),
                new DeliveryExecutionControlAction(
                    ControlType::SUBMISSION,
                    ControlStatus::SUCCESS,
                ),
                Carbon::now(),
                $this->deliveryExecution,
                null,
                null,
                null,
            ),
            'proctorActions' => new DeliveryExecutionAcsLogMessage(
                $this->deliveryExecution->getId(),
                null,
                'ok',
                $acsControl,
            ),
            'resetAction' => new DeliveryExecutionAcsLogMessage(
                $this->deliveryExecution->getId(),
                null,
                'ok',
                ['action' => 'reset'],
            ),
            'extraMessage' => new stdClass(),
        ];
    }

    private function mockProvisionedEventsSettings(?array $allowedEvents): void
    {
        $this->deliverProvisionedEventsSettingsRepository
            ->method('findAssessmentLogSettings')
            ->willReturn([
                'proctorActions' => $allowedEvents,
                'systemActions' => $allowedEvents,
                'testTakerActions' => $allowedEvents,
            ]);
    }
}
