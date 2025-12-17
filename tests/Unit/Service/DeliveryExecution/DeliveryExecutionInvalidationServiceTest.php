<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\DeliveryExecution;

use App\DataStore\Sender\DataStoreSenderInterface;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionServiceInterface;
use App\Service\DeliveryExecution\DeliveryExecutionInvalidationService;
use Carbon\Carbon;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DeliveryExecutionInvalidationServiceTest extends TestCase
{
    private DeliveryExecutionServiceInterface|MockObject $deliveryExecutionService;
    private DataStoreSenderInterface|MockObject $dataStoreSender;
    private LoggerInterface|MockObject $logger;
    private DeliveryExecutionInvalidationService $service;

    protected function setUp(): void
    {
        $this->deliveryExecutionService = $this->createMock(DeliveryExecutionServiceInterface::class);
        $this->dataStoreSender = $this->createMock(DataStoreSenderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new DeliveryExecutionInvalidationService(
            $this->deliveryExecutionService,
            $this->dataStoreSender,
            $this->logger,
        );
    }

    public function testInvalidateMarksDeliveryExecutionAsInvalidated(): void
    {
        $deliveryExecution = new DeliveryExecution(
            'test-id',
            'delivery-id',
            'tenant-id',
            Carbon::now(),
            ['result_id' => 'test-result'],
            'test-session',
            new DeliveryExecutionExtraStateData(),
        );

        $userLogin = 'test_user';

        $this->deliveryExecutionService
            ->expects($this->once())
            ->method('saveDeliveryExecution')
            ->with($deliveryExecution);

        $this->dataStoreSender
            ->expects($this->once())
            ->method('send')
            ->with($deliveryExecution);

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $this->service->invalidate($deliveryExecution, $userLogin);

        $this->assertTrue($deliveryExecution->isResultInvalidated());
        $this->assertEquals($userLogin, $deliveryExecution->getinvalidation()->getInvalidatedBy());
    }

    public function testInvalidateTriggersDataStoreSync(): void
    {
        $deliveryExecution = new DeliveryExecution(
            'test-id',
            'delivery-id',
            'tenant-id',
            Carbon::now(),
            ['result_id' => 'test-result', 'battery_id' => 'battery-123'],
            'test-session',
            new DeliveryExecutionExtraStateData(),
        );

        $userLogin = 'test_user';

        $this->dataStoreSender
            ->expects($this->once())
            ->method('send')
            ->with($deliveryExecution);

        $this->service->invalidate($deliveryExecution, $userLogin);

        $this->assertTrue($deliveryExecution->isResultInvalidated());
        $this->assertEquals($userLogin, $deliveryExecution->getinvalidation()->getInvalidatedBy());
    }
}
