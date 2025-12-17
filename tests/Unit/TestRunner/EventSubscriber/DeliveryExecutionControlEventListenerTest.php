<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\EventSubscriber;

use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorIdentity;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorRole;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionControlAction;
use App\Messenger\Message\DeliveryExecution\ExecutionControlMessage;
use App\Qti\Extractor\QtiVariableExtractor;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\TestRunner\Event\Control\ControlStatus;
use App\TestRunner\Event\Control\ControlType;
use App\TestRunner\Event\Control\DeliveryExecutionControlEvent;
use App\TestRunner\EventSubscriber\DeliveryExecutionControlEventListener;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\TestSessionTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use PHPUnit\Framework\MockObject\MockObject;
use qtism\runtime\tests\Route;
use qtism\runtime\tests\RouteItem;

class DeliveryExecutionControlEventListenerTest extends TestCase
{
    use TestSessionTrait;
    use DomainTestingTrait;

    private RequestStack $requestStack;
    private DeliveryExecutionPropertyService $deliveryExecutionPropertyService;
    private MessageBusInterface $messageBus;
    private DeliveryExecutionControlEventListener $listener;
    private QtiVariableExtractor $qtiVariableExtractor;


    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->deliveryExecutionPropertyService = $this->createMock(DeliveryExecutionPropertyService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->qtiVariableExtractor = $this->createMock(QtiVariableExtractor::class);

        $this->listener = new DeliveryExecutionControlEventListener(
            $this->requestStack,
            $this->deliveryExecutionPropertyService,
            $this->messageBus,
            $this->qtiVariableExtractor,
        );
    }

    public function testOnExecutionControl(): void
    {
        // Mock the event
        $deliveryExecution = $this->createTestDeliveryExecution();
        $event = new DeliveryExecutionControlEvent($deliveryExecution, ControlType::PAUSE);

        // Mock the request and its headers
        $request = $this->createMock(Request::class);
        $request = new Request([], [], [], [], [], [
            'REMOTE_ADDR' => '127.0.0.6',
            'HTTP_X_FORWARDED_FOR' => '98.76.54.231,34.149.23.32,130.211.1.171',
        ]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        // Mock DeliveryExecutionPropertyService to return a test session
        $testSession = $this->createTestSession($deliveryExecution->getId());
        $route = $this->createMock(Route::class);
        $testSession->method('getRoute')->willReturn($route);
        $this->deliveryExecutionPropertyService->method('fetchTestSession')->willReturn($testSession);

        // Mock Route and its current method
        $route->method('valid')->willReturn(true);
        $routeItem = $this->createMock(RouteItem::class);
        $route->method('current')->willReturn($routeItem);

        // Expect the message bus to be dispatched
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (ExecutionControlMessage $message) use ($event, $deliveryExecution) {
                    $data = $message->jsonSerialize();

                    $this->assertNotEmpty($data['actorIdentity']);
                    $this->assertInstanceOf(DeliveryExecutionActorIdentity::class, $data['actorIdentity']);

                    // Assert the values in the ExecutionControlMessage
                    $actorIdentity = $data['actorIdentity']->jsonSerialize();
                    $this->assertEquals(DeliveryExecutionActorRole::ROLE_TEST_TAKER, $actorIdentity['role']);

                    $this->assertNotEmpty($data['action']);
                    $this->assertInstanceOf(DeliveryExecutionControlAction::class, $data['action']);
                    $action = $data['action']->jsonSerialize();

                    $this->assertEquals(ControlType::PAUSE, $action['type']);
                    $this->assertEquals(ControlStatus::SUCCESS, $action['status']);

                    $this->assertEquals($deliveryExecution->getId(), $data['deliveryExecution']['id']);
                    $this->assertEquals($deliveryExecution->getStatus(), $data['deliveryExecution']['status']);
                    return true;
                }),
            );

        // Call the method
        $this->listener->onExecutionControl($event);
    }
}
