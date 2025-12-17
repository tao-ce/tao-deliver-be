<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA ;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message;

abstract class PlagiarismStatusMessage
{
    public function __construct(
        private string $id,
        private string $createdAt,
        private string $assessmentId,
        private string $itemId,
        private string $responseId,
        private string $status,
        private ?string $href = '',
    ) {
    }

    abstract public function getProvider(): string;

    public function getId(): string
    {
        return $this->id;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getAssessmentId(): string
    {
        return $this->assessmentId;
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
        return $this->href ?? '';
    }
}
