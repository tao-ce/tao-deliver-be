<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Qti\Model;

class DownloadableAsset
{
    public function __construct(
        private readonly Asset $asset,
        private readonly string $downloadUrl,
        private readonly string $path,
    ) {
    }

    public function getFilename(): string
    {
        return $this->asset->getFilename();
    }

    public function __toString(): string
    {
        $innerString = $this->asset->__toString();

        return $innerString . ',bucket_path,' . $this->getPath() . ',download_url,' . $this->getDownloadUrl();
    }

    public function equals($obj): bool
    {
        return $this->asset->equals($obj);
    }

    public function getBaseType(): int
    {
        return $this->asset->getBaseType();
    }

    public function getCardinality(): int
    {
        return $this->asset->getCardinality();
    }

    public function getData()
    {
        return $this->asset->getData();
    }

    public function getMimeType()
    {
        return $this->asset->getMimeType();
    }

    public function hasFilename(): bool
    {
        return $this->asset->hasFilename();
    }

    public function getStream()
    {
        return $this->asset->getStream();
    }

    public function getIdentifier(): string
    {
        return $this->asset->getIdentifier();
    }

    public function getSize()
    {
        return $this->asset->getSize();
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getValue()
    {
        return $this->asset->getValue();
    }

    public function getDownloadUrl(): string
    {
        return $this->downloadUrl;
    }
}
