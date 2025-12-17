<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\Enrollment\Model;

use JsonSerializable;
use OAT\Bundle\DocumentManagerBundle\Document\AbstractDocument;

class Enrollment extends AbstractDocument implements JsonSerializable
{
    public function __construct(
        protected $id,
        public readonly string $campaignId,
        public readonly string $campaignName,
        public readonly string $sessionId,
        public readonly string $sessionName,
        public readonly string $sessionTemplateId,
        public readonly string $sessionTemplateName,
        public readonly ?array $testCategory,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'campaignId' => $this->campaignId,
            'campaignName' => $this->campaignName,
            'sessionId' => $this->sessionId,
            'sessionName' => $this->sessionName,
            'sessionTemplateId' => $this->sessionTemplateId,
            'sessionTemplateName' => $this->sessionTemplateName,
            'testCategory' => $this->testCategory,
        ];
    }

    public function getSessionTemplateId(): string
    {
        return $this->sessionTemplateId;
    }

    public function getSessionTemplateName(): string
    {
        return $this->sessionTemplateName;
    }

    public function getCampaignId(): string
    {
        return $this->campaignId;
    }

    public function getCampaignName(): string
    {
        return $this->campaignName;
    }

    public function getTestCategory(): array
    {
        return $this->testCategory ?? [];
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getSessionName(): string
    {
        return $this->sessionName;
    }
}
