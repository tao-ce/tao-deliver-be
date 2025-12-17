<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Action\Asset;

use App\Action\AbstractSignedUrlAction;
use App\Response\AssetResponse;
use App\Service\Asset\MimeTypeDetectorService;
use App\Validator\SignedUrlRequestValidator;
use League\Flysystem\FilesystemReader;
use Symfony\Component\HttpFoundation\Response;

class DownloadAssetAction extends AbstractSignedUrlAction
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

    public function getResponse(
        $resource,
        ?string $assetMimeType,
        ?int $assetTimestamp,
        ?int $assetSize,
        string $filename,
    ): AssetResponse {
        return new AssetResponse(
            $resource,
            $assetMimeType,
            $assetTimestamp,
            $assetSize,
            Response::HTTP_OK,
            [
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            ],
        );
    }
}
