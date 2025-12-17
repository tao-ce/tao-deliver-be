<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action;

use App\Response\AssetResponse;
use App\Service\Asset\MimeTypeDetectorService;
use App\Validator\SignedUrlRequestValidator;
use Exception;
use League\Flysystem\FilesystemReader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class AbstractSignedUrlAction
{
    /** @var SignedUrlRequestValidator */
    private $validator;

    /** @var MimeTypeDetectorService */
    private $mimeTypeDetectorService;

    public function __construct(
        SignedUrlRequestValidator $validator,
        MimeTypeDetectorService $mimeTypeDetectorService,
    ) {
        $this->validator = $validator;
        $this->mimeTypeDetectorService = $mimeTypeDetectorService;
    }

    public function __invoke(Request $request): AssetResponse
    {
        $requestData = $this->validator->getValidatedRequestParameters($request);

        try {
            $resource = $this->getStorage()->readStream($requestData[SignedUrlRequestValidator::PATH_PARAMETER]);
            $assetMimeType = $this->mimeTypeDetectorService->detect($this->getStorage(), $requestData[SignedUrlRequestValidator::PATH_PARAMETER]);
            $assetTimestamp = $this->getStorage()->lastModified($requestData[SignedUrlRequestValidator::PATH_PARAMETER]) ?: null;
            $assetSize = $this->getStorage()->fileSize($requestData[SignedUrlRequestValidator::PATH_PARAMETER]) ?: null;
        } catch (Exception $exception) {
            throw new HttpException(Response::HTTP_NOT_FOUND, $exception->getMessage(), $exception);
        }

        return $this->getResponse(
            $resource,
            $assetMimeType,
            $assetTimestamp,
            $assetSize,
            $requestData[SignedUrlRequestValidator::PATH_PARAMETER],
        );
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
        );
    }

    abstract public function getStorage(): FilesystemReader;
}
