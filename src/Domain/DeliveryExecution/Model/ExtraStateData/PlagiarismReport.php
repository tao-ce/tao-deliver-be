<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA ;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData;

use Carbon\Carbon;

class PlagiarismReport
{
    public function __construct(
        private string $provider,
        private string $id,
        private Carbon $createdAt,
        private string $itemId,
        private string $responseId,
        private string $status,
        private string $href = '',
    ) {
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCreatedAt(): Carbon
    {
        return $this->createdAt;
    }

    public function getItemId(): string
    {
        return $this->itemId;
    }

    public function getResponseId(): string
    {
        return $this->responseId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getHref(): string
    {
        return $this->href;
    }
}
