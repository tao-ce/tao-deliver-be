<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ItemEnricher;

use App\Generator\Attachment\AttachmentUrlGenerator;
use App\TestRunner\ItemEnricher\Contract\ItemStateEnricherInterface;
use qtism\common\datatypes\files\FileHash;

class FileNameAssignEnricher implements ItemStateEnricherInterface
{
    public function __construct(private AttachmentUrlGenerator $urlGenerator)
    {
    }

    /**
     * return modified ItemState
     */
    public function enrich(mixed $responseVariable): mixed
    {
        if (!isset($responseVariable['response']['base'][FileHash::FILE_HASH_KEY])) {
            return $responseVariable;
        }

        $downloadUrl = $this->urlGenerator->generateDownloadUrl(
            $responseVariable['response']['base'][FileHash::FILE_HASH_KEY]['id'],
            $responseVariable['response']['base'][FileHash::FILE_HASH_KEY]['name'],
        );
        $responseVariable['response']['base'][FileHash::FILE_HASH_KEY]['link'] = $downloadUrl;
        $responseVariable['response']['base'][FileHash::FILE_HASH_KEY]['downloadUrl'] = $downloadUrl;

        return $responseVariable;
    }
}
