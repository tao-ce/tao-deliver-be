<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025-2026 (original work) Open Assessment Technologies SA ;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\DeliveryExecution\DeliveryExecutionDeleter;
use App\Service\DeliveryExecution\DeliveryExecutionDeletionService;
use App\Service\DeliveryExecution\DeliveryExecutionResultManagerService;
use App\Service\DeliveryExecution\DeliveryExecutionUploadsManagerService;
use App\Service\DeliveryExecution\LoggerAwareDeliveryExecutionService;
use App\TestRunner\Service\ExternalTimerService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @covers \App\Service\DeliveryExecution\DeliveryExecutionDeletionService
 */
final class DeliveryExecutionDeletionServiceTest extends TestCase
{
    private DeliveryExecutionDeleter|MockObject $deliveryExecutionDeleteService;
    private LoggerAwareDeliveryExecutionService|MockObject $deliveryExecutionRepository;
    private DeliveryExecutionUploadsManagerService|MockObject $deliveryExecutionUploadsManager;
    private DeliveryExecutionResultManagerService|MockObject $deliveryExecutionResultManagerService;
    private ExternalTimerService|MockObject $externalTimerService;
    private DeliveryExecutionDeletionService $subject;

    protected function setUp(): void
    {
        $this->deliveryExecutionDeleteService = $this->createMock(DeliveryExecutionDeleter::class);
        $this->deliveryExecutionRepository = $this->createMock(LoggerAwareDeliveryExecutionService::class);
        $this->deliveryExecutionUploadsManager = $this->createMock(DeliveryExecutionUploadsManagerService::class);
        $this->deliveryExecutionResultManagerService = $this->createMock(DeliveryExecutionResultManagerService::class);
        $this->externalTimerService = $this->createMock(ExternalTimerService::class);

        $this->subject = new DeliveryExecutionDeletionService(
            $this->deliveryExecutionDeleteService,
            $this->deliveryExecutionRepository,
            $this->deliveryExecutionUploadsManager,
            $this->deliveryExecutionResultManagerService,
            $this->externalTimerService,
        );
    }

    public function testDropDeliveryExecutionByIdWhenDeliveryExecutionExists(): void
    {
        $deliveryExecutionId = 'test-delivery-execution-id';
        $deliveryExecution = $this->createMock(DeliveryExecution::class);

        $this->deliveryExecutionRepository
            ->expects(self::once())
            ->method('findDeliveryExecution')
            ->with($deliveryExecutionId)
            ->willReturn($deliveryExecution);

        $this->deliveryExecutionDeleteService
            ->expects(self::once())
            ->method('delete')
            ->with($deliveryExecution);

        $this->deliveryExecutionRepository
            ->expects(self::once())
            ->method('deleteDeliveryExecution')
            ->with($deliveryExecution);

        $this->externalTimerService
            ->expects(self::never())
            ->method('deleteServerTimer');

        $this->deliveryExecutionUploadsManager
            ->expects(self::never())
            ->method('dropUploads');

        $this->deliveryExecutionResultManagerService
            ->expects(self::never())
            ->method('dropResults');


        $this->subject->removeDeliveryExecutionById($deliveryExecutionId);
    }

    public function testDropDeliveryExecutionByIdWhenDeliveryExecutionDoesNotExist(): void
    {
        $deliveryExecutionId = 'non-existent-delivery-execution-id';

        $this->deliveryExecutionRepository
            ->expects(self::once())
            ->method('findDeliveryExecution')
            ->with($deliveryExecutionId)
            ->willReturn(null);

        // These services should NOT be called if the delivery execution doesn't exist
        $this->deliveryExecutionDeleteService
            ->expects(self::never())
            ->method('delete');

        $this->deliveryExecutionRepository
            ->expects(self::never())
            ->method('deleteDeliveryExecution');

        // These services should still be called for cleanup, regardless of whether the object existed
        $this->externalTimerService
            ->expects(self::once())
            ->method('deleteServerTimer')
            ->with($deliveryExecutionId);

        $this->deliveryExecutionUploadsManager
            ->expects(self::once())
            ->method('dropUploads')
            ->with($deliveryExecutionId);

        $this->deliveryExecutionResultManagerService
            ->expects(self::once())
            ->method('dropResults')
            ->with($deliveryExecutionId);

        $this->subject->removeDeliveryExecutionById($deliveryExecutionId);
    }
}
