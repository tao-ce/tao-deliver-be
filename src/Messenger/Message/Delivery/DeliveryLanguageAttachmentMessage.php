<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Messenger\Message\Delivery;

readonly class DeliveryLanguageAttachmentMessage
{
    public function __construct(
        private string $deliveryId,
        private string $locale,
        private ?string $packagePath = null,
        private ?string $packageRef = null,
    ) {
    }

    public function getDeliveryId(): string
    {
        return $this->deliveryId;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getPackagePath(): ?string
    {
        return $this->packagePath;
    }

    public function getPackageRef(): ?string
    {
        return $this->packageRef;
    }
}
