<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Attachment;

readonly class ItemCategoryBasedAttachmentRegistry
{
    private const CATEGORY_PREFIX = 'x-tao-attachment-';

    public function __construct(
        private AttachmentRegistry $registry,
    ) {
    }

    public function resolveAttachments(string $tenantId, array $categories): array
    {
        if (!preg_match_all(
            sprintf('/^%s(?<ids>.*)$/m', preg_quote(self::CATEGORY_PREFIX, '/')),
            implode("\n", $categories),
            $matches,
        )) {
            return [];
        }

        $attachments = $this->registry->resolveAttachments($tenantId, $matches['ids']);
        $result = [];
        foreach ($attachments as $key => $attachment) {
            $result[sprintf('%s%s', self::CATEGORY_PREFIX, $key)] = $attachment;
        }
        return $result;
    }
}
