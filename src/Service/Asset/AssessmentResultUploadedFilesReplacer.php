<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Asset;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Generator\Attachment\AttachmentUrlGenerator;
use App\Qti\Model\Asset;
use App\Qti\Model\DownloadableAsset;
use League\Flysystem\FilesystemReader;
use qtism\common\datatypes\QtiFile;
use qtism\data\results\AssessmentResult;
use qtism\data\results\ItemResult;
use qtism\data\results\ItemVariable;
use qtism\data\results\ResultResponseVariable;
use qtism\data\state\Value;

class AssessmentResultUploadedFilesReplacer
{
    public function __construct(
        private FilesystemReader $uploadedFileStorage,
        private FileSizeNormalizer $sizeNormalizer,
        private $fileSizeLimit,
        private ?array $mimeTypeList,
        private AttachmentUrlGenerator $urlGenerator,
    ) {
    }

    public function replace(AssessmentResult $assessmentResult): void
    {
        foreach ($assessmentResult->getItemResults() as $itemResult) {
            $this->replaceUploadedFilePathsInItemResult($itemResult);
        }
    }

    private function replaceUploadedFilePathsInItemResult(ItemResult $itemResult): void
    {
        if (!$itemResult->hasItemVariables()) {
            return;
        }

        foreach ($itemResult->getItemVariables() as $itemVariable) {
            $this->replaceUploadedFilePathsInItemVariable($itemVariable);
        }
    }

    private function replaceUploadedFilePathsInItemVariable(ItemVariable $itemVariable): void
    {
        if (!$itemVariable instanceof ResultResponseVariable) {
            return;
        }

        foreach ($itemVariable->getCandidateResponse()->getValues() as $value) {
            $this->replaceUploadedFilePathsInValue($value);
        }
    }

    private function replaceUploadedFilePathsInValue(Value $value): void
    {
        $file = $value->getValue();

        if (!$file instanceof QtiFile) {
            return;
        }

        $path = $file->getIdentifier();

        $asset = new Asset(
            $this->shouldBeReplaced($file, $path),
            $file,
            $path,
            $this->uploadedFileStorage,
        );

        $downloadUrl = $this->getDownloadUrl($path, $asset);
        if ($downloadUrl !== null) {
            $asset = new DownloadableAsset($asset, $downloadUrl, $path);
        }

        $value->setValue($asset);
    }

    private function shouldBeReplaced(QtiFile $file, string $path): bool
    {
        return $this->fileSizeUnderLimit($path)
            && $this->fileTypeAllowed($file, $path);
    }

    private function fileSizeUnderLimit(string $path): bool
    {
        return $this->uploadedFileStorage->fileSize($path) <= $this->sizeNormalizer->sizeToBytes($this->fileSizeLimit);
    }

    private function fileTypeAllowed(QtiFile $file, string $path): bool
    {
        if ($this->mimeTypeList === null) {
            return true;
        }

        return in_array(
            $file->getMimeType() ?: $this->uploadedFileStorage->mimeType($path),
            $this->mimeTypeList,
            true,
        );
    }

    private function getDownloadUrl(string $path, Asset $asset): ?string
    {
        if ($this->uploadedFileStorage->has($path)) {
            return $this->urlGenerator->generateDownloadUrl($path, $asset->getFilename(), forFrontEnd: false);
        }

        return null;
    }
}
