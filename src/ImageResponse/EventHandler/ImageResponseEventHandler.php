<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\ImageResponse\EventHandler;

use App\ImageResponse\Input\ImageResponse;
use App\ImageResponse\Service\ImageResponseWriterService;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ImageResponseEventHandler
{
    public function __construct(
        private LoggerInterface $auditDeliveryExecutionLogger,
        private ImageResponseWriterService $service,
        private DeliveryExecutionService $deliveryExecutionService,
        private LockFactory $lockFactory,
    ) {
    }

    public function __invoke(ImageResponse $message): void
    {
        $context = compact('message');
        if (!$message->isValid()) {
            $this->auditDeliveryExecutionLogger->warning(
                'Skipped handling invalid ImageResponse message',
                $context,
            );
            return;
        }

        $id = $this->deliveryExecutionService->assembleDeliveryExecutionId(
            $message->userId,
            $message->qrCodeMetadata->deliveryId,
            $message->qrCodeMetadata->attemptId,
            $message->tenantId,
        );
        $this->auditDeliveryExecutionLogger->debug(
            "[$id] Start to handle ImageResponse message",
            $context,
        );
        $lock = $this->lockFactory->createLock(
            "delivery_execution_update_$id",
        );
        $lock->acquire(true);
        $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecution($id);
        if (!$deliveryExecution) {
            $this->auditDeliveryExecutionLogger->warning(
                "[$id] Delivery execution not found",
            );
            $lock->release();
            return;
        }
        if ($deliveryExecution->getFinishedAt() === null) {
            $this->auditDeliveryExecutionLogger->warning(
                sprintf('[%s] Delivery execution not finished', $deliveryExecution->getId()),
            );
            $lock->release();
            return;
        }

        try {
            $this->deliveryExecutionService->saveDeliveryExecution(
                $this->service->write($deliveryExecution, $message),
            );
        } finally {
            $lock->release();
        }
    }
}
