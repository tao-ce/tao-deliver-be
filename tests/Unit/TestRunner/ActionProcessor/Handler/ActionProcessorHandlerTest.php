<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\ActionProcessor\Handler;

use App\Domain\Battery\Model\BatteryDistribution;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Environment\FeatureFlagAdapterInterface;
use App\Logger\ExceptionContextLogger\ExceptionContextLoggerService;
use App\Lti\LtiCustomSettings;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\TestRunner\ActionProcessor\ActionProcessorInterface;
use App\TestRunner\ActionProcessor\Handler\ActionProcessorHandler;
use App\TestRunner\ActionProcessor\Handler\CantPerformActionException;
use App\TestRunner\ActionProcessor\Registry\ActionProcessorRegistry;
use App\TestRunner\Event\TestSessionEndEvent;
use App\TestRunner\Service\ActionIdProvider;
use App\TestRunner\Service\BatteryDistributionService;
use App\TestRunner\Service\BatteryNavigationService;
use App\TestRunner\Service\RealTimeService;
use App\Tests\Traits\DomainTestingTrait;
use Carbon\Carbon;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ActionProcessorHandlerTest extends TestCase
{
    use DomainTestingTrait;

    private ActionProcessorHandler $actionProcessorHandler;

    private BatteryNavigationService&MockObject $batteryNavigationServiceMock;
    private BatteryDistributionService&MockObject $batteryDistributionServiceMock;
    private ActionProcessorRegistry&MockObject $actionProcessorRegistryMock;
    private DeliveryExecutionServiceInterface&MockObject $deliveryExecutionServiceMock;
    private EventDispatcherInterface&MockObject $eventDispatcherMock;
    private LtiCustomSettings&MockObject $ltiCustomSettings;
    private ExceptionContextLoggerService&MockObject $exceptionContextLoggerService;
    private ActionIdProvider&MockObject $actionIdProviderMock;

    public function setUp(): void
    {
        $this->batteryNavigationServiceMock = $this->createMock(BatteryNavigationService::class);
        $this->batteryDistributionServiceMock = $this->createMock(BatteryDistributionService::class);
        $this->actionProcessorRegistryMock = $this->createMock(ActionProcessorRegistry::class);
        $this->deliveryExecutionServiceMock = $this->createMock(DeliveryExecutionServiceInterface::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $this->ltiCustomSettings = $this->createMock(LtiCustomSettings::class);
        $this->exceptionContextLoggerService = $this->createMock(ExceptionContextLoggerService::class);
        $this->actionIdProviderMock = $this->createMock(ActionIdProvider::class);

        $this->actionProcessorHandler = new ActionProcessorHandler(
            $this->createMock(LoggerInterface::class),
            $this->batteryNavigationServiceMock,
            $this->batteryDistributionServiceMock,
            $this->actionProcessorRegistryMock,
            $this->deliveryExecutionServiceMock,
            $this->eventDispatcherMock,
            $this->ltiCustomSettings,
            $this->exceptionContextLoggerService,
            $this->actionIdProviderMock,
            $this->createMock(RealTimeService::class),
            $this->createMock(RequestStack::class),
            $this->createMock(FeatureFlagAdapterInterface::class),
        );
    }

    public function testReturnsSuccess(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution();

        $this->deliveryExecutionServiceMock
            ->method('findDeliveryExecutionOrFail')
            ->with('deliveryExecutionId')
            ->willReturn($deliveryExecution);

        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('saveDeliveryExecution')
            ->with($deliveryExecution);

        $actionProcessorMock = $this->createMock(ActionProcessorInterface::class);

        $this->actionProcessorRegistryMock
            ->expects($this->exactly(3))
            ->method('get')
            ->withConsecutive(['action1'], ['action2'], ['action3'])
            ->willReturn($actionProcessorMock);

        $actionProcessorMock
            ->expects($this->exactly(3))
            ->method('process')
            ->withConsecutive(
                [$deliveryExecution, $this->getActionsMock()[0]],
                [$deliveryExecution, $this->getActionsMock()[1]],
                [$deliveryExecution, $this->getActionsMock()[2]],
            )
            ->willReturn(['success' => true]);

        $this->actionIdProviderMock
            ->expects(self::exactly(6))
            ->method('set')
            ->withConsecutive(
                ['action1id'],
                [null],
                ['action2id'],
                [null],
                ['action3id'],
                [null],
            );

        $response = $this->actionProcessorHandler->handle('deliveryExecutionId', $this->getActionsMock());

        $this->assertEquals([
            ['success' => true],
            ['success' => true],
            ['success' => true],
        ], $response);
    }

    public function testEmitsSessionEndEventWhenDeliveryExecutionIsFinished(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution();
        $this->deliveryExecutionServiceMock
            ->method('findDeliveryExecutionOrFail')
            ->with($deliveryExecution->getId())
            ->willReturn($deliveryExecution);

        $actionProcessorMock = $this->createMock(ActionProcessorInterface::class);
        $actionProcessorMock
            ->method('process')
            ->willReturnCallback(
                static function (DeliveryExecution $deliveryExecution, array $params) {
                    $deliveryExecution->close();
                    return $params;
                },
            );
        $this->actionProcessorRegistryMock
            ->method('get')
            ->willReturnMap([
                ['action', $actionProcessorMock],
            ]);

        $this->eventDispatcherMock
            ->expects($this->once())
            ->method('dispatch')
            ->with(new TestSessionEndEvent($this->actionProcessorHandler::class, $deliveryExecution));

        $this->actionProcessorHandler->handle(
            $deliveryExecution->getId(),
            [['name' => 'action', 'id' => 'action']],
        );
    }

    public function testSkipsSessionEndEventWhenDryRunDeliveryExecutionIsFinished(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution();
        $this->deliveryExecutionServiceMock
            ->method('findDeliveryExecutionOrFail')
            ->with($deliveryExecution->getId())
            ->willReturn($deliveryExecution);

        $this->ltiCustomSettings
            ->method('isDryRunEnabled')
            ->willReturn(true);

        $actionProcessorMock = $this->createMock(ActionProcessorInterface::class);
        $actionProcessorMock
            ->method('process')
            ->willReturnCallback(
                static function (DeliveryExecution $deliveryExecution, array $params) {
                    $deliveryExecution->close();
                    return $params;
                },
            );
        $this->actionProcessorRegistryMock
            ->method('get')
            ->willReturnMap([
                ['action', $actionProcessorMock],
            ]);

        $this->eventDispatcherMock
            ->expects($this->never())
            ->method('dispatch');
        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('deleteDeliveryExecution')
            ->with($deliveryExecution);

        $this->actionProcessorHandler->handle(
            $deliveryExecution->getId(),
            [['name' => 'action', 'id' => 'action']],
        );
    }

    public function testDoesntFailWhenDeletingDryRunDeliveryExecutionOnSessionEndEvent(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution();
        $this->deliveryExecutionServiceMock
            ->method('findDeliveryExecutionOrFail')
            ->with($deliveryExecution->getId())
            ->willReturn($deliveryExecution);

        $this->ltiCustomSettings
            ->method('isDryRunEnabled')
            ->willReturn(true);

        $actionProcessorMock = $this->createMock(ActionProcessorInterface::class);
        $actionProcessorMock
            ->method('process')
            ->willReturnCallback(
                static function (DeliveryExecution $deliveryExecution, array $params) {
                    $deliveryExecution->close();
                    return $params;
                },
            );
        $this->actionProcessorRegistryMock
            ->method('get')
            ->willReturnMap([
                ['action', $actionProcessorMock],
            ]);

        $this->eventDispatcherMock
            ->expects($this->never())
            ->method('dispatch');
        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('deleteDeliveryExecution')
            ->willThrowException(new Exception());

        $this->actionProcessorHandler->handle(
            $deliveryExecution->getId(),
            [['name' => 'action', 'id' => 'action']],
        );
    }

    public function testReturnsErrorWhenTryingToPerformAnActionOnTerminatedDeliveryExecution(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution();

        $deliveryExecution->setFinishedAt(Carbon::now())
            ->setStatus(DeliveryExecution::STATUS_TERMINATED);

        $this->deliveryExecutionServiceMock
            ->method('findDeliveryExecutionOrFail')
            ->with('deliveryExecutionId')
            ->willReturn($deliveryExecution);

        $actionProcessorMock = $this->createMock(ActionProcessorInterface::class);
        $actionProcessorMock->expects(self::once())->method('validateAvailability')
            ->with(DeliveryExecution::STATUS_TERMINATED)
            ->willThrowException(CantPerformActionException::becauseTestSessionIsTerminated('action1'));

        $this->actionProcessorRegistryMock
            ->expects($this->exactly(1))
            ->method('get')
            ->withConsecutive(['action1'])
            ->willReturn($actionProcessorMock);

        $this->actionIdProviderMock
            ->expects(self::exactly(2))
            ->method('set')
            ->withConsecutive(
                ['action1id'],
                [null],
            );

        $response = $this->actionProcessorHandler->handle(
            'deliveryExecutionId',
            [['name' => 'action1', 'id' => 'action1id']],
        );

        $this->assertEquals(
            [
                [
                    'success' => false,
                    'name' => 'action1',
                    'id' => 'action1id',
                    'errorCode' => 100,
                    'errorMessage' => 'Can\'t perform the action "action1" because the test session is terminated',
                    'values' => [],
                    '_exception' => CantPerformActionException::class,
                ],
            ],
            $response,
        );
    }

    public function testReturnsErrorWhenTryingToPerformAnActionOnSuspendedDeliveryExecution(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution();

        $deliveryExecution->setFinishedAt(Carbon::now())
            ->setStatus(DeliveryExecution::STATUS_SUSPENDED);

        $this->deliveryExecutionServiceMock
            ->method('findDeliveryExecutionOrFail')
            ->with('deliveryExecutionId')
            ->willReturn($deliveryExecution);

        $actionProcessorMock = $this->createMock(ActionProcessorInterface::class);
        $actionProcessorMock->expects(self::once())->method('validateAvailability')
            ->with(DeliveryExecution::STATUS_SUSPENDED)
            ->willThrowException(CantPerformActionException::becauseTestSessionIsSuspended('action1'));

        $this->actionProcessorRegistryMock
            ->expects($this->exactly(1))
            ->method('get')
            ->withConsecutive(['action1'])
            ->willReturn($actionProcessorMock);

        $this->actionIdProviderMock
            ->expects(self::exactly(2))
            ->method('set')
            ->withConsecutive(
                ['action1id'],
                [null],
            );

        $response = $this->actionProcessorHandler->handle(
            'deliveryExecutionId',
            [['name' => 'action1', 'id' => 'action1id']],
        );

        $this->assertEquals(
            [
                [
                    'success' => false,
                    'name' => 'action1',
                    'id' => 'action1id',
                    'errorCode' => 102,
                    'errorMessage' => 'Can\'t perform the action "action1" because the test session is suspended',
                    'values' => [],
                    '_exception' => CantPerformActionException::class,
                ],
            ],
            $response,
        );
    }

    public function testNotSkipsDeliveryExecutionDeleteWhenDryRunDeliveryExecutionIsFinishedWithBattery(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            ltiLaunchParameters: [
                'battery_id' => 'batteryId',
            ],
        );

        $this->deliveryExecutionServiceMock
            ->method('findDeliveryExecutionOrFail')
            ->with($deliveryExecution->getId())
            ->willReturn($deliveryExecution);

        $this->ltiCustomSettings
            ->method('isDryRunEnabled')
            ->willReturn(true);

        $actionProcessorMock = $this->createMock(ActionProcessorInterface::class);
        $actionProcessorMock
            ->method('process')
            ->willReturnCallback(
                static function (DeliveryExecution $deliveryExecution, array $params) {
                    $deliveryExecution->close();
                    return $params;
                },
            );

        $batteryDistribution = $this->createMock(BatteryDistribution::class);
        $this->batteryNavigationServiceMock
            ->expects($this->once())
            ->method('getBatteryDistribution')
            ->with($deliveryExecution)
            ->willReturn($batteryDistribution);

        $this->batteryNavigationServiceMock
            ->expects($this->once())
            ->method('getNextDeliveryExecution')
            ->willReturn(null);

        $this->batteryDistributionServiceMock
            ->expects($this->once())
            ->method('deleteDeliveryExecutionsLinkedToBatteryDistribution');

        $this->actionProcessorRegistryMock
            ->method('get')
            ->willReturnMap([
                ['action', $actionProcessorMock],
            ]);

        $this->actionProcessorHandler->handle(
            $deliveryExecution->getId(),
            [['name' => 'action', 'id' => 'action']],
        );
    }

    public function testSkipsDeliveryExecutionDeleteWhenDryRunDeliveryExecutionIsFinishedWithBatteryAndNotLastInaSequence(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution(
            ltiLaunchParameters: [
                'battery_id' => 'batteryId',
            ],
        );

        $this->deliveryExecutionServiceMock
            ->method('findDeliveryExecutionOrFail')
            ->with($deliveryExecution->getId())
            ->willReturn($deliveryExecution);

        $this->ltiCustomSettings
            ->method('isDryRunEnabled')
            ->willReturn(true);

        $actionProcessorMock = $this->createMock(ActionProcessorInterface::class);
        $actionProcessorMock
            ->method('process')
            ->willReturnCallback(
                static function (DeliveryExecution $deliveryExecution, array $params) {
                    $deliveryExecution->close();
                    return $params;
                },
            );

        $batteryDistribution = $this->createMock(BatteryDistribution::class);
        $this->batteryNavigationServiceMock
            ->expects($this->once())
            ->method('getBatteryDistribution')
            ->with($deliveryExecution)
            ->willReturn($batteryDistribution);

        $nextDeliveryExecution = $this->createMock(DeliveryExecution::class);
        $this->batteryNavigationServiceMock
            ->expects($this->once())
            ->method('getNextDeliveryExecution')
            ->willReturn($nextDeliveryExecution);

        $this->batteryDistributionServiceMock
            ->expects($this->never())
            ->method('deleteDeliveryExecutionsLinkedToBatteryDistribution');

        $this->actionProcessorRegistryMock
            ->method('get')
            ->willReturnMap([
                ['action', $actionProcessorMock],
            ]);

        $this->actionProcessorHandler->handle(
            $deliveryExecution->getId(),
            [['name' => 'action', 'id' => 'action']],
        );
    }

    public function testStopsProcessingFollowingActionsWhenAnEarlyActionFails(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution();

        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('findDeliveryExecutionOrFail')
            ->with('deliveryExecutionId')
            ->willReturn($deliveryExecution);

        $this->deliveryExecutionServiceMock
            ->expects($this->once())
            ->method('saveDeliveryExecution')
            ->with($deliveryExecution);

        $actionProcessorMock = $this->createMock(ActionProcessorInterface::class);
        $this->actionProcessorRegistryMock
            ->expects($this->once())
            ->method('get')
            ->with('action1')
            ->willThrowException(new Exception('error', 1));

        $actionProcessorMock
            ->expects($this->exactly(0))
            ->method('process');

        $this->actionIdProviderMock
            ->expects(self::exactly(3))
            ->method('set')
            ->with(null);

        $response = $this->actionProcessorHandler->handle('deliveryExecutionId', $this->getActionsMock());

        $this->assertEquals([
            [
                'success' => false,
                'name' => 'action1',
                'id' => 'action1id',
                'errorCode' => 1,
                'errorMessage' => 'error',
                'values' => [],
                '_exception' => Exception::class,
            ],
            [
                'success' => false,
                'name' => 'action2',
                'id' => 'action2id',
                'errorCode' => 0,
                'errorMessage' => 'Action processing has been terminated',
                'values' => [],
                '_exception' => CantPerformActionException::class,
            ],
            [
                'success' => false,
                'name' => 'action3',
                'id' => 'action3id',
                'errorCode' => 0,
                'errorMessage' => 'Action processing has been terminated',
                'values' => [],
                '_exception' => CantPerformActionException::class,
            ],
        ], $response);
    }

    private function getActionsMock(): array
    {
        return [
            ['name' => 'action1', 'id' => 'action1id'],
            ['name' => 'action2', 'id' => 'action2id'],
            ['name' => 'action3', 'id' => 'action3id'],
        ];
    }
}
