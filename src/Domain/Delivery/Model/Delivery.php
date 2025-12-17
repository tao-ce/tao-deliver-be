<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\Delivery\Model;

use App\Domain\Deletable;
use DateTimeInterface;
use OAT\Bundle\DocumentManagerBundle\Document\AbstractDocument;
use OAT\Bundle\QtiBundle\Model\TestAwareInterface;

class Delivery extends AbstractDocument implements Deletable, TestAwareInterface
{
    public const LOCALE_FOLDER_NAME = '__locale';
    private const METADATA_CONFIGURATION = 'metadata';

    public function __construct(
        string $id,
        private readonly string $tenantId,
        private readonly DateTimeInterface $createdAt,
        private readonly string $compactTestFilePath,
        private array $configuration = [],
        private readonly array $qtiItemsMapping = [],
        private readonly ?string $packageRef = null,
        private bool $isDeleted = false,
        private ?string $draftId = null,
        private ?string $mainLocale = null,
        private array $supportedLocales = [],
        private array $translations = [],
        private bool $isDisabled = false,
    ) {
        $this->id = $id;
        $this->setConfiguration($configuration);
    }

    public function isUnlisted(): bool
    {
        return $this->configuration['unlisted'] ?? false;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getDeliveryId(): string
    {
        return $this->getId();
    }

    public function getQtiCompactTestFilePath(): string
    {
        return $this->compactTestFilePath;
    }

    public function getQtiSdkEncodedTestSession(): null
    {
        return null;
    }

    public function setQtiSdkEncodedTestSession(?string $qtiSdkEncodedTestSession): static
    {
        return $this;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function setConfiguration(array $configuration): static
    {
        if (!isset($configuration[self::METADATA_CONFIGURATION]) && $this->hasMetadata()) {
            $configuration[self::METADATA_CONFIGURATION] = $this->getMetadata();
        }

        $this->configuration = $configuration;
        return $this;
    }

    public function getQtiItemsMapping(): array
    {
        return $this->qtiItemsMapping;
    }

    public function hasMetadata(): bool
    {
        return isset($this->configuration[self::METADATA_CONFIGURATION]);
    }

    public function getMetadata(): array
    {
        return $this->hasMetadata() ? $this->configuration[self::METADATA_CONFIGURATION] : [];
    }

    public function getPackageRef(): ?string
    {
        return $this->packageRef;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;
        return $this;
    }

    public function getDraftId(): ?string
    {
        return $this->draftId;
    }

    public function setDraftId(?string $draftId): Delivery
    {
        $this->draftId = $draftId;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getMetadataPropertyValues(string $property): array
    {
        return array_values($this->getMetadata()[$property] ?? []);
    }

    public function getMetadataPropertyValue(string $property): ?string
    {
        return $this->getMetadataPropertyValues($property)[0] ?? null;
    }

    public function getMainLocale(): ?string
    {
        return $this->mainLocale;
    }

    public function setMainLocale(string $locale): static
    {
        $this->mainLocale = $locale;

        return $this;
    }

    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    public function setSupportedLocales(array $supportedLocales): static
    {
        $this->supportedLocales = $supportedLocales;

        return $this;
    }

    public function isSupportedLocale(string $locale): bool
    {
        return in_array($locale, $this->getSupportedLocales());
    }

    public function getTranslations(): array
    {
        return $this->translations;
    }

    public function setTranslations(array $translations): static
    {
        $this->translations = $translations;

        return $this;
    }

    public function getIsDisabled(): bool
    {
        return $this->isDisabled;
    }

    public function setIsDisabled(bool $isDisabled): static
    {
        $this->isDisabled = $isDisabled;

        return $this;
    }

    public function isMultiLanguage(): bool
    {
        $mainLocale = $this->getMainLocale();
        $supportedLocales = $this->getSupportedLocales();

        if (null === $mainLocale) {
            return count($supportedLocales) > 1;
        }

        $supportedLocalesMap = array_flip($supportedLocales);
        unset($supportedLocalesMap[$mainLocale]);
        return !empty($supportedLocalesMap);
    }
}
