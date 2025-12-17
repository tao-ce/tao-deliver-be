<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message;

class PublicationMessage
{
    public function __construct(
        private readonly string $publicationId,
        private readonly string $tenantId,
        private readonly string $base64ZipPath,
        private readonly string $packageRef,
        private readonly array $configuration,
        private readonly ?string $deliveryId = null,
        private readonly array $translations = [],
    ) {
    }

    public function getPublicationId(): string
    {
        return $this->publicationId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getBase64ZipPath(): string
    {
        return $this->base64ZipPath;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function getPackageRef(): string
    {
        return $this->packageRef;
    }

    public function getDeliveryId(): ?string
    {
        return $this->deliveryId;
    }

    public function getTranslations(): array
    {
        return $this->translations;
    }
}
