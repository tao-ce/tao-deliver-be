<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\ImageResponse\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\ImageResponse\Input\ImageResponse;
use App\ImageResponse\Output\Attachment;
use LogicException;

readonly class ImageResponseWriterService
{
    public function __construct(private ImageResponseReaderService $readerService)
    {
    }

    public function write(DeliveryExecution $deliveryExecution, ImageResponse $message): DeliveryExecution
    {
        if (!$message->isValid()) {
            throw new LogicException('Image response invalid');
        }

        if (
            $deliveryExecution->getTenantId() !== $message->tenantId
            || $deliveryExecution->getUserId() !== $message->userId
        ) {
            return $deliveryExecution;
        }

        $itemId = $message->qrCodeMetadata->itemId;
        $attachments = $this->readerService->createAttachmentList(
            $deliveryExecution->getItemAttachments($itemId),
        )->addAttachment(
            new Attachment(
                $message->assetId,
                $message->qrCodeMetadata->responseId,
                $message->uploadedAt,
                $message->qrCodeMetadata->pageNumber,
            ),
        );

        return $deliveryExecution->setItemAttachments($itemId, $attachments->toArray());
    }
}
