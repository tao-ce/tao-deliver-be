<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Manager;

use App\Traits\FilesystemTrait;
use OAT\Library\QtiItemJsonCompilation\Asset\AssetDownloader;
use OAT\Library\QtiItemJsonCompilation\Exception\CompilationException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class PciAssetManager
{
    use FilesystemTrait;

    protected const PCI_FOLDER_NAME = '_PCI';

    /** @var AssetDownloader */
    private $assetDownloader;

    /** @var LoggerInterface */
    private $auditPlatformLogger;

    public function __construct(AssetDownloader $assetDownloader, LoggerInterface $auditPlatformLogger)
    {
        $this->assetDownloader = $assetDownloader;
        $this->auditPlatformLogger = $auditPlatformLogger;
    }

    /**
     * - Download PCI assets into the storage if they don't exist
     * - Modify assets paths in provided `$portableElements` array
     *
     * @throws CompilationException
     * @throws RuntimeException
     */
    public function compileAssets(array $portableElements, string $itemSourcePath, string $compilationId): array
    {
        if (!isset($portableElements['pci'])) {
            return $portableElements;
        }

        foreach ($portableElements['pci'] as &$elements) {
            foreach ($elements as &$pci) {
                if (!isset($pci['runtime']['hook'])) {
                    throw new RuntimeException('Mandatory hook resource is missing in portable elements');
                }

                $basePath = md5($this->assetDownloader->getContent($itemSourcePath, $pci['runtime']['hook']) . ($pci['version'] ?? '0'));

                foreach ($pci['runtime'] as &$assets) {
                    if (!is_array($assets)) {
                        $assets = $this->copyAsset(
                            $itemSourcePath,
                            $assets,
                            $basePath,
                            $compilationId,
                        );

                        continue;
                    }

                    foreach ($assets as &$asset) {
                        $asset = $this->copyAsset(
                            $itemSourcePath,
                            $asset,
                            $basePath,
                            $compilationId,
                        );
                    }
                }
            }
        }

        return $portableElements;
    }

    private function copyAsset(string $itemSourcePath, string $assetUrl, string $basePath, string $compilationId): string
    {
        $itemDestinationPath = $this->buildPathFor(static::PCI_FOLDER_NAME, $basePath);

        // relative path in PCI storage, otherwise it will be saved in root
        if (($position = strpos($assetUrl, 'runtime/')) !== false) {
            $relativePath = dirname(substr($assetUrl, $position + 8));

            if ($relativePath && '.' !== $relativePath) {
                $itemDestinationPath = $this->buildPathFor($itemDestinationPath, $relativePath);
            }
        }

        $resultingFileName = $this->assetDownloader->download(
            $itemSourcePath,
            $itemDestinationPath,
            $assetUrl,
        );

        $resourcePath = $this->buildPathFor($itemDestinationPath, $resultingFileName);

        $this->auditPlatformLogger->info(
            sprintf('[%s] PCI resource %s has been moved to the storage', $compilationId, $resourcePath),
        );

        return $resourcePath;
    }
}
