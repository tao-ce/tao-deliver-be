<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Model;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemReader;
use OAT\Bundle\QtiBundle\Model\Asset as QtiAsset;
use qtism\common\datatypes\QtiFile;

class Asset extends QtiAsset
{
    public function __construct(
        private bool $isSerializable,
        private QtiFile $file,
        string $path,
        FilesystemReader $qtiAssetManagerStorage,
    ) {
        parent::__construct($path, $qtiAssetManagerStorage, $file->getFilename());
    }

    public function __toString(): string
    {
        $filename = str_replace(',', '.', $this->getFilename());
        if (!$this->isSerializable) {
            return $filename;
        }

        try {
            return sprintf(
                '%s,%s,base64,%s',
                $filename,
                $this->file->getMimeType() ?: $this->getMimeType(),
                base64_encode($this->getData()),
            );
        } catch (FilesystemException) {
            return $filename;
        }
    }
}
