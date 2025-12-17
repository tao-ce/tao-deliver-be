<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Extractor;

use App\Qti\Exception\QtiPackageExtractorException;
use App\Traits\FilesystemTrait;
use Exception;
use League\Flysystem\FilesystemWriter;
use League\Flysystem\PathPrefixer;
use Psr\Log\LoggerInterface;
use ZipArchive;

readonly class QtiPackageExtractor
{
    use FilesystemTrait;

    private FilesystemWriter $storage;
    private PathPrefixer $prefixer;

    public function __construct(
        private FilesystemWriter $qtiPackageExtractorStorage,
        private LoggerInterface $auditPlatformLogger,
        string $prefix,
    ) {
        $this->storage = $qtiPackageExtractorStorage;
        $this->prefixer = new PathPrefixer($prefix);
    }

    /**
     * @throws QtiPackageExtractorException
     */
    public function extract(string $packageZipStream, string $extractionTarget, ?string $localePath = null): string
    {
        try {
            $packageExtractionPath = $this->applyStoragePathPrefix($this->buildPathFor($extractionTarget, $localePath));
            $temporaryZipFileName = sprintf('%s_%s.zip', $extractionTarget, uniqid());

            $this->storage->write($temporaryZipFileName, $packageZipStream);

            $zip = new ZipArchive();
            $zip->open($this->applyStoragePathPrefix($temporaryZipFileName));
            $zip->extractTo($packageExtractionPath);

            $this->storage->delete($temporaryZipFileName);

            return $packageExtractionPath;
        } catch (Exception $exception) {
            throw new QtiPackageExtractorException(
                sprintf('Package extraction failed: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception,
            );
        }
    }

    private function applyStoragePathPrefix(string $path): string
    {
        return $this->prefixer->prefixPath($path);
    }
}
