<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

trait AttachmentsTrait
{
    private array $attachments = [];

    public function getItemAttachments(string $itemIdentifier): array
    {
        return $this->attachments[$itemIdentifier] ?? [];
    }

    public function withItemAttachments(string $itemIdentifier, array $attachments): self
    {
        $that = clone $this;
        $that->attachments[$itemIdentifier] = $attachments;
        return $that;
    }

    public function withAttachments(array $attachments): self
    {
        $that = clone $this;
        $that->attachments = $attachments;
        return $that;
    }
}
