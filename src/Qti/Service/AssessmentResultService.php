<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Service;

use App\Qti\Exception\ResultCannotBePersistedException;
use App\Qti\Exception\ResultNotFoundException;
use App\Service\Asset\MimeTypeDetectorService;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;

class AssessmentResultService
{
    private const RESULT_ID_REPLACEMENT_MAP = [
        '/' => '_',
        '\\' => '_',
    ];

    public function __construct(
        private readonly FilesystemOperator $deliveryExecutionResultStorage,
        private readonly MimeTypeDetectorService $mimeTypeDetectorService,
    ) {
    }

    /**
     * @return resource
     */
    public function getStreamedAssessmentResult(string $resultId)
    {
        $exception = null;
        try {
            $resultsResource = $this->deliveryExecutionResultStorage->readStream($this->normalizeResultId($resultId));
        } catch (UnableToReadFile $exception) {
        }

        if (null !== $exception || false === $resultsResource) {
            throw ResultNotFoundException::createFromResultId($resultId, $exception);
        }

        return $resultsResource;
    }

    public function getAssessmentResultMetadata(string $resultId): AssessmentResultMetadata
    {
        $exception = null;
        $resultPath = $this->normalizeResultId($resultId);
        try {
            $size = $this->deliveryExecutionResultStorage->fileSize($resultPath);
            $timestamp = $this->deliveryExecutionResultStorage->lastModified($resultPath);
            $mimeType = $this->mimeTypeDetectorService->detect($this->deliveryExecutionResultStorage, $resultPath);
        } catch (FilesystemException $exception) {
        }

        if (null !== $exception || false === $size || false === $timestamp) {
            throw ResultNotFoundException::createFromResultId($resultId, $exception);
        }

        return new AssessmentResultMetadata($mimeType, $size, $timestamp);
    }

    public function persist(string $resultId, string $assessmentResultXml): void
    {
        try {
            $this->deliveryExecutionResultStorage->write(
                $this->normalizeResultId($resultId),
                $assessmentResultXml,
            );
        } catch (UnableToWriteFile) {
            throw ResultCannotBePersistedException::createFromResultId($resultId);
        }
    }

    /**
     * @throws FilesystemException
     */
    public function delete(string $resultId): void
    {
        try {
            $this->deliveryExecutionResultStorage->delete($this->normalizeResultId($resultId));
        } catch (UnableToDeleteFile $exception) {
            throw ResultNotFoundException::createFromResultId($resultId, $exception);
        }
    }

    private function normalizeResultId(string $resultId): string
    {
        return sprintf(
            '%s.xml',
            str_replace(
                array_keys(self::RESULT_ID_REPLACEMENT_MAP),
                array_values(self::RESULT_ID_REPLACEMENT_MAP),
                $resultId,
            ),
        );
    }
}
