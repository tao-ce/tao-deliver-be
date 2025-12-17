<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Attachments;

use App\Response\AssetResponse;
use App\Service\Asset\MimeTypeDetectorService;
use League\Flysystem\FilesystemReader;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DownloadFileAction
{
    public function __construct(
        private readonly FilesystemReader $deliveryExecutionUploadsStorage,
        private readonly MimeTypeDetectorService $mimeTypeDetectorService,
    ) {
    }

    public function __invoke(string $path): AssetResponse
    {
        try {
            $mimeType = $this->mimeTypeDetectorService->detect($this->deliveryExecutionUploadsStorage, $path);
            return new AssetResponse(
                $this->deliveryExecutionUploadsStorage->readStream($path),
                $mimeType,
                $this->deliveryExecutionUploadsStorage->lastModified($path) ?: null,
                $this->deliveryExecutionUploadsStorage->fileSize($path) ?: null,
                headers: $mimeType === 'text/csv'
                    ? [
                        'Content-Disposition' => sprintf('attachment; filename="%s"', 'report.csv'),
                    ]
                    : [],
            );
        } catch (UnableToReadFile $exception) {
            throw new HttpException(Response::HTTP_NOT_FOUND, $exception->getMessage(), $exception);
        }
    }
}
