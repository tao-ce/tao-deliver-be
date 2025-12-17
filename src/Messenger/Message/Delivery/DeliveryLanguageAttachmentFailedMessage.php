<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Messenger\Message\Delivery;

readonly class DeliveryLanguageAttachmentFailedMessage
{
    public function __construct(
        private string $deliveryId,
        private string $tenantId,
        private array $configuration,
        private array $translations,
        private array $errors,
    ) {
    }

    public function getDeliveryId(): string
    {
        return $this->deliveryId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }


    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function getTranslations(): array
    {
        return $this->translations;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
