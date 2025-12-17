<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\Publication\Model;

use OAT\Bundle\DocumentManagerBundle\Document\AbstractDocument;

class Publication extends AbstractDocument
{
    public const STATUS_CREATED = 'created';
    public const STATUS_STARTED = 'started';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    private string $status;
    private string $tenantId;
    private ?string $deliveryId;
    private string $packagePath;
    private string $packageRef;
    private array $packageConfiguration;
    private array $reports;
    private ?string $locale;

    public function __construct(
        string $id,
        string $tenantId,
        string $packagePath,
        string $packageRef = '',
        array $packageConfiguration = [],
        array $reports = [],
        string $status = self::STATUS_CREATED,
        ?string $deliveryId = null,
        ?string $locale = null,
    ) {
        $this->id = $id;
        $this->setTenantId($tenantId)
            ->setDeliveryId($deliveryId)
            ->setPackagePath($packagePath)
            ->setPackageRef($packageRef)
            ->setPackageConfiguration($packageConfiguration)
            ->setReports($reports)
            ->setStatus($status)
            ->setLocale($locale);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getDeliveryId(): ?string
    {
        return $this->deliveryId;
    }

    public function getPackagePath(): string
    {
        return $this->packagePath;
    }

    public function getPackageConfiguration(): array
    {
        return $this->packageConfiguration;
    }

    public function getReports(): array
    {
        return $this->reports;
    }

    public function getPackageRef(): string
    {
        return $this->packageRef;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        $this->addUpdate('status');

        return $this;
    }

    public function setReports(array $reports): self
    {
        $this->reports = $reports;
        $this->addUpdate('reports');

        return $this;
    }

    public function setDeliveryId(?string $deliveryId): self
    {
        $this->deliveryId = $deliveryId;
        $this->addUpdate('deliveryId');

        return $this;
    }

    /**
     * @param string $tenantId
     * @return Publication
     */
    public function setTenantId(string $tenantId): self
    {
        $this->tenantId = $tenantId;
        $this->addUpdate('tenantId');
        return $this;
    }

    /**
     * @param string $packagePath
     * @return Publication
     */
    public function setPackagePath(string $packagePath): self
    {
        $this->packagePath = $packagePath;
        $this->addUpdate('packagePath');
        return $this;
    }

    /**
     * @param array $packageConfiguration
     * @return Publication
     */
    public function setPackageConfiguration(array $packageConfiguration): self
    {
        $this->packageConfiguration = $packageConfiguration;
        $this->addUpdate('packageConfiguration');
        return $this;
    }

    /**
     * @param string $packageRef
     * @return Publication
     */
    public function setPackageRef(string $packageRef): Publication
    {
        $this->packageRef = $packageRef;
        $this->addUpdate('packageRef');
        return $this;
    }

    /**
     * @param ?string $locale
     * @return Publication
     */
    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;
        $this->addUpdate('locale');
        return $this;
    }
}
