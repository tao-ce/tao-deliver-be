<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ItemEnricher;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Generator\Attachment\AttachmentUrlGenerator;
use League\Flysystem\FilesystemReader;
use OAT\Library\EnvironmentManagementClient\Exception\EnvironmentManagementClientException;
use OAT\Library\EnvironmentManagementClient\Repository\ConfigurationRepositoryInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TrendItemPropertiesEnricher
{
    private const CACHE_TTL = 60 * 60 * 24;
    private const CACHE_KEY_PREFIX = 'trend_item_extra_asset';
    private const VERSION_PREFIX = 'v';
    private const ROOT_PREFIX = 'trendItem';

    private DeliveryExecution $deliveryExecution;
    private string $elementName;
    private string $locale = 'en-ZZ';
    private ?string $extraAssetVersion = null;

    public function __construct(
        private readonly CacheInterface $cacheChain,
        private readonly ConfigurationRepositoryInterface $configurationRepository,
        private readonly FilesystemReader $extraAssetStorage,
        private readonly AttachmentUrlGenerator $urlGenerator,
        private readonly string $extraAssetUrl,
        private readonly string $extraAssetPrefix,
    ) {
    }

    public function enrich(DeliveryExecution $deliveryExecution, string $elementName, array $properties): array
    {
        $this->deliveryExecution = $deliveryExecution;
        $this->elementName = $elementName;
        if (!empty($properties['language'])) {
            $this->locale = $properties['language'];
        }

        $properties['assets'] = $this->cacheChain->get($this->createCacheKey(), function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL);
            return [
                'module' => $this->createModuleAssetLinks(),
                'unit' => $this->createUnitAssetLinks(),
            ];
        });

        return $properties;
    }

    private function createModuleAssetLinks(): array
    {
        $links = [];

        foreach ($this->extraAssetStorage->listContents($this->getPrefixedPath('module')) as $module) {
            if (!$module->isDir()) {
                continue;
            }

            $moduleName = basename($module->path());
            $localizedModule = "{$module->path()}/$this->locale/";
            $localizedModulePrefixLength = strlen($localizedModule);
            foreach ($this->extraAssetStorage->listContents($localizedModule, FilesystemReader::LIST_DEEP) as $asset) {
                if (!$asset->isFile()) {
                    continue;
                }

                $links[$moduleName][substr($asset->path(), $localizedModulePrefixLength)] =
                    $this->createPublicUrl($asset->path());
            }
        }

        return $links;
    }

    private function createUnitAssetLinks(): array
    {
        $links = [];

        $localizedUnit = $this->getPrefixedPath("unit/$this->elementName/$this->locale/");
        $localizedUnitPrefixLength = strlen($localizedUnit);
        foreach ($this->extraAssetStorage->listContents($localizedUnit, FilesystemReader::LIST_DEEP) as $asset) {
            if (!$asset->isFile()) {
                continue;
            }

            $links[substr($asset->path(), $localizedUnitPrefixLength)] = $this->createPublicUrl($asset->path());
        }

        return $links;
    }

    private function createPublicUrl(string $path): string
    {
        return $this->extraAssetUrl
            ? "$this->extraAssetUrl/$this->extraAssetPrefix/$path?version={$this->getExtraAssetVersion()}"
            : $this->urlGenerator->generateDownloadUrl("$this->extraAssetPrefix/$path");
    }

    private function getPrefixedPath(string $prefix): string
    {
        return sprintf('%s/%s', self::ROOT_PREFIX, $prefix);
    }

    private function getExtraAssetVersion(): string
    {
        if (null !== $this->extraAssetVersion) {
            return $this->extraAssetVersion;
        }

        try {
            $this->extraAssetVersion = $this->configurationRepository->find(
                $this->deliveryExecution->getTenantId(),
                'extraAssetVersion',
            )->getStringValue();
        } catch (EnvironmentManagementClientException) {
            $this->extraAssetVersion = '1';
        }

        return self::VERSION_PREFIX . $this->extraAssetVersion;
    }

    private function createCacheKey(): string
    {
        return sprintf(
            '%s_%s_%s_%s',
            self::CACHE_KEY_PREFIX,
            $this->getExtraAssetVersion(),
            $this->elementName,
            $this->locale,
        );
    }
}
