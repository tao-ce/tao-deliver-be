<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\Delivery;

use App\Domain\Delivery\Model\Delivery;
use App\Repository\DeliveryRepository;
use App\Service\Delivery\DeleteDeliveryService;
use Exception;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;

class DeleteDeliveryServiceTest extends TestCase
{
    private $deliveryRepository;
    private $qtiPackageExtractorStorage;
    private $qtiCompiledDeliveriesStorage;
    private $service;

    protected function setUp(): void
    {
        $this->deliveryRepository = $this->createMock(DeliveryRepository::class);
        $this->qtiPackageExtractorStorage = $this->createMock(FilesystemOperator::class);
        $this->qtiCompiledDeliveriesStorage = $this->createMock(FilesystemOperator::class);

        $this->service = new DeleteDeliveryService(
            $this->deliveryRepository,
            $this->qtiPackageExtractorStorage,
            $this->qtiCompiledDeliveriesStorage,
        );
    }

    public function testSoftDeleteSuccessfully(): void
    {
        $delivery = $this->createMock(Delivery::class);

        $delivery
            ->expects($this->once())
            ->method('setIsDeleted')
            ->with(true);
        $this->deliveryRepository
            ->expects($this->once())
            ->method('save')
            ->with($delivery);

        $this->service->softDelete($delivery);
    }

    public function testSoftDeleteLogsErrorOnFailure(): void
    {
        $delivery = $this->createMock(Delivery::class);
        $delivery
            ->method('getId')
            ->willReturn('deliveryId');

        $delivery
            ->expects($this->once())
            ->method('setIsDeleted')
            ->with(true);
        $this->deliveryRepository
            ->expects($this->once())
            ->method('save')
            ->willThrowException(new Exception('Save failed'));

        $this->expectException(Exception::class);
        $this->service->softDelete($delivery);
    }

    public function testHardDeleteSuccessfully(): void
    {
        $delivery = $this->createMock(Delivery::class);
        $delivery
            ->method('getId')
            ->willReturn('deliveryId');

        $this->deliveryRepository
            ->expects($this->once())
            ->method('delete')
            ->with($delivery);

        $this->qtiPackageExtractorStorage
            ->expects($this->once())
            ->method('directoryExists')
            ->with('deliveryId')
            ->willReturn(true);

        $this->qtiPackageExtractorStorage
            ->expects($this->once())
            ->method('deleteDirectory')
            ->with('deliveryId');

        $this->qtiCompiledDeliveriesStorage
            ->expects($this->once())
            ->method('directoryExists')
            ->with('deliveryId')
            ->willReturn(true);

        $this->qtiCompiledDeliveriesStorage
            ->expects($this->once())
            ->method('deleteDirectory')
            ->with('deliveryId');

        $this->service->hardDelete($delivery);
    }

    public function testHardDeleteHandlesNonExistentDirectories(): void
    {
        $delivery = $this->createMock(Delivery::class);
        $delivery
            ->method('getId')
            ->willReturn('deliveryId');

        $this->deliveryRepository
            ->expects($this->once())
            ->method('delete')
            ->with($delivery);

        $this->qtiPackageExtractorStorage
            ->expects($this->once())
            ->method('directoryExists')
            ->with('deliveryId')
            ->willReturn(false);

        $this->qtiPackageExtractorStorage
            ->expects($this->never())
            ->method('deleteDirectory');

        $this->qtiCompiledDeliveriesStorage
            ->expects($this->once())
            ->method('directoryExists')
            ->with('deliveryId')
            ->willReturn(false);

        $this->qtiCompiledDeliveriesStorage
            ->expects($this->never())
            ->method('deleteDirectory');

        $this->service->hardDelete($delivery);
    }

    public function testHardDeleteLogsFailureError(): void
    {
        $delivery = $this->createMock(Delivery::class);
        $delivery
            ->method('getId')
            ->willReturn('deliveryId');

        $this->deliveryRepository
            ->expects($this->once())
            ->method('delete')
            ->willThrowException(new Exception('Delete failed'));

        $this->expectException(Exception::class);
        $this->service->hardDelete($delivery);
    }
}
