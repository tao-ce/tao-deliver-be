<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Handler;

use App\Messenger\Message\Delivery\DeliveryLanguageAttachmentMessage;
use App\Messenger\Message\Delivery\SynchronousDeliveryLanguageAttachmentMessage;
use App\Repository\DeliveryRepository;
use App\Service\Delivery\AttachLanguageToDeliveryService;
use App\Traits\FilesystemTrait;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class DeliveryLanguageAttachmentHandler
{
    use FilesystemTrait;

    public function __construct(
        private readonly DeliveryRepository $deliveryRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly AttachLanguageToDeliveryService $attachLanguageToDeliveryService,
    ) {
    }

    public function __invoke(DeliveryLanguageAttachmentMessage $message): void
    {
        $deliveryId = $message->getDeliveryId();
        $locale = $message->getLocale();

        $this->attachLanguageToDeliveryService->handleLocaleAttachment(
            $this->deliveryRepository->find($deliveryId),
            $locale,
            $message->getPackagePath(),
            $message->getPackageRef(),
            $message instanceof SynchronousDeliveryLanguageAttachmentMessage,
        );
    }
}
