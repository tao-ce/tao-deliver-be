<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\ImageResponse\Service;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\ImageResponse\Output\Attachment;
use App\ImageResponse\Output\AttachmentList;
use App\TestItemAttachment\Service\AttachmentRegistry;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Twig\Environment;

readonly class ImageResponseReaderService
{
    public function __construct(
        private LoggerInterface $auditDeliveryExecutionLogger,
        private ObjectNormalizer $normalizer,
        private AttachmentRegistry $attachmentRegistry,
        private Environment $twig,
    ) {
    }

    public function read(DeliveryExecution $deliveryExecution, string $itemId, array $itemState): array
    {
        $attachmentList = $this->createAttachmentList($deliveryExecution->getItemAttachments($itemId));
        $attachments = $this->attachmentRegistry->resolveAttachments(
            $deliveryExecution->getTenantId(),
            $attachmentList->getIds(),
        );
        foreach ($attachmentList as $attachmentDocument) {
            if (empty($attachments[$attachmentDocument->id])) {
                continue;
            }
            try {
                @$itemState[$attachmentDocument->responseId]['response']['base']['string'] .= $this->twig->render(
                    '@ImageResponse/Image.twig',
                    $attachments[$attachmentDocument->id],
                );
            } catch (RuntimeException $exception) {
                $this->auditDeliveryExecutionLogger->critical(
                    "Failed to attach a scanned image to an item response. {$exception->getMessage()}",
                    compact('exception'),
                );
            }
        }

        return $itemState;
    }

    public function createAttachmentList(array $attachments): AttachmentList
    {
        try {
            $attachments = array_map(
                fn(array $attachment): Attachment => $this->normalizer->denormalize($attachment, Attachment::class),
                $attachments,
            );
        } catch (RuntimeException $exception) {
            $attachments = [];
            $this->auditDeliveryExecutionLogger->critical(
                "Failed to attach scanned images to an item. {$exception->getMessage()}",
                compact('exception'),
            );
        }
        return new AttachmentList($attachments);
    }
}
