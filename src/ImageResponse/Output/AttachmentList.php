<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\ImageResponse\Output;

use ArrayIterator;
use JsonSerializable;

/**
 * @method Attachment current()
 * @method string key()
 */
final class AttachmentList extends ArrayIterator implements JsonSerializable
{
    /**
     * @param Attachment[] $attachments
     */
    public function __construct(array $attachments)
    {
        usort(
            $attachments,
            static fn(
                Attachment $a,
                Attachment $b,
            ) => $a->responseId <=> $b->responseId ?: $a->pageNumber <=> $b->pageNumber,
        );
        $mappedAttachments = [];
        foreach ($attachments as $attachment) {
            $mappedAttachments[$this->createKey($attachment)] = $attachment;
        }
        parent::__construct($mappedAttachments);
    }

    public function getIds(): array
    {
        $ids = [];
        foreach ($this as $attachment) {
            $ids[] = $attachment->id;
        }
        return $ids;
    }

    public function addAttachment(Attachment $attachment): self
    {
        $key = $this->createKey($attachment);
        /** @var ?Attachment $existingAttachment */
        $existingAttachment = $this[$key] ?? null;
        if (!$existingAttachment || $existingAttachment->createdAt <= $attachment->createdAt) {
            $this[$key] = $attachment;
        }
        return $this;
    }

    public function toArray(): array
    {
        $result = [];
        foreach ($this as $attachment) {
            $result[] = $attachment->toArray();
        }
        return $result;
    }

    public function jsonSerialize(): array
    {
        return array_values($this->getArrayCopy());
    }

    private function createKey(Attachment $attachment): string
    {
        return implode('_', [$attachment->responseId, $attachment->pageNumber]);
    }
}
