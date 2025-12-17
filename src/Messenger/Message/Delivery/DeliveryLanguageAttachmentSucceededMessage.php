<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Messenger\Message\Delivery;

readonly class DeliveryLanguageAttachmentSucceededMessage
{
    public function __construct(
        private string $deliveryId,
        private string $tenantId,
        private array $configuration,
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
}
