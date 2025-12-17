<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Asset;

use App\Action\AbstractSignedUrlAction;
use App\Service\Asset\MimeTypeDetectorService;
use App\Validator\SignedUrlRequestValidator;
use League\Flysystem\FilesystemReader;

class GetAssetAction extends AbstractSignedUrlAction
{
    public function __construct(
        SignedUrlRequestValidator $validator,
        MimeTypeDetectorService $mimeTypeDetectorService,
        private readonly FilesystemReader $qtiAssetManagerStorage,
    ) {
        parent::__construct(
            $validator,
            $mimeTypeDetectorService,
        );
    }

    public function getStorage(): FilesystemReader
    {
        return $this->qtiAssetManagerStorage;
    }
}
