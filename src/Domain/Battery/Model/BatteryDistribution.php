<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\Battery\Model;

use OAT\Bundle\DocumentManagerBundle\Document\AbstractDocument;

class BatteryDistribution extends AbstractDocument
{
    public const DOCUMENT_KEY_DELIMITER = '#';

    public function __construct(
        protected $id,
        public readonly string $userId,
        public readonly Battery $battery,
        private ?string $locale = null,
    ) {
        $this->addUpdate('userId');
        $this->addUpdate('battery');
        $this->addUpdate('locale');
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
        $this->addUpdate('locale');
    }
}
