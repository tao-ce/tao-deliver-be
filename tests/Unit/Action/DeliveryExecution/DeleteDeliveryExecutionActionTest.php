<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Tests\Unit\Action\DeliveryExecution;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Service\DeliveryExecution\DeliveryExecutionDeleter;
use App\Repository\DeliveryExecutionRepository;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Action\DeliveryExecution\DeleteDeliveryExecutionAction;
use App\Messenger\Message\DeliveryExecutionAcsLogMessage;
use App\TestRunner\Service\InteractionMessageService;
use App\Tests\Traits\DomainTestingTrait;
use App\Tests\Traits\TestSessionTrait;
use Carbon\Carbon;
use PHPUnit\Framework\MockObject\MockObject;

class DeleteDeliveryExecutionActionTest extends TestCase
{
    use DomainTestingTrait;
    use TestSessionTrait;

    private DeliveryExecutionRepository|MockObject $deliveryExecutionRepository;
    private DeliveryExecutionDeleter|MockObject $deliveryExecutionDeleter;
    private DeliveryExecutionPropertyService|MockObject $deliveryExecutionPropertyService;
    private MessageBusInterface|MockObject $messageBus;
    private LoggerInterface|MockObject $logger;
    private DeleteDeliveryExecutionAction $subject;
    private DeliveryExecution $deliveryExecution;
    private InteractionMessageService|MockObject $interactionMessageService;

    public function setUp(): void
    {
        parent::setUp();
        $this->deliveryExecutionRepository = $this->createMock(DeliveryExecutionRepository::class);
        $this->deliveryExecutionDeleter = $this->createMock(DeliveryExecutionDeleter::class);
        $this->deliveryExecutionPropertyService = $this->createMock(DeliveryExecutionPropertyService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->interactionMessageService = $this->createMock(InteractionMessageService::class);


        $this->deliveryExecution = $this->createTestDeliveryExecution(
            ltiLaunchParameters: [
                'resource_link_id' => 'resourceLinkId',
            ],
        );

        $this->deliveryExecutionRepository
            ->method('find')
            ->with($this->deliveryExecution->getId())
            ->willReturn($this->deliveryExecution);

        $this->subject = new DeleteDeliveryExecutionAction(
            $this->deliveryExecutionRepository,
            $this->deliveryExecutionDeleter,
            $this->deliveryExecutionPropertyService,
            $this->messageBus,
            $this->logger,
            $this->interactionMessageService,
        );
    }

    public function testInvoke()
    {
        $request = $this->createRequest();
        $response = $this->subject->__invoke(
            $this->deliveryExecution->getId(),
            $this->deliveryExecution->getTenantId(),
            $request,
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(Response::HTTP_ACCEPTED, $response->getStatusCode());
    }

    public function testInvokeWithInvalidTenant()
    {
        $deliveryExecutionMock = $this->createTestDeliveryExecution();

        $repositoryMock = $this->createMock(DeliveryExecutionRepository::class);
        $repositoryMock->method('find')->willReturn($deliveryExecutionMock);

        $this->deliveryExecutionRepository = $repositoryMock;
        $request = new Request();

        $this->expectException(AccessDeniedHttpException::class);
        $this->subject->__invoke($deliveryExecutionMock->getId(), 'invalidTenant', $request);
    }

    public function testProduceInteractingMessage()
    {
        $request = $this->createRequest();

        $this->deliveryExecutionDeleter
            ->expects($this->once())
            ->method('delete')
            ->with($this->deliveryExecution);

        $this->interactionMessageService
            ->expects($this->once())
            ->method('createAndPublishInteractionMessage');

        $this->subject->__invoke(
            $this->deliveryExecution->getId(),
            $this->deliveryExecution->getTenantId(),
            $request,
        );
    }

    private function createRequest(array $extra = []): Request
    {
        return new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($extra));
    }

    private function getAcsData(string $deliveryExecutionId): array
    {
        return [
            "extra" => [
                "acsLog" => [
                    "delivery_execution_id" => $deliveryExecutionId,
                    "userName" => "test",
                    "sub" => "admin",
                    "action" => "reset",
                    "incident_time" => Carbon::now(),
                    "incident_time_manual" => false,
                    "reason_code" => "20020",
                    "reason_msg" => "testmessage",
                ],
            ],
        ];
    }
}
