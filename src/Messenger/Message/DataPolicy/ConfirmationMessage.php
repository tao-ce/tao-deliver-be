<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message\DataPolicy;

readonly class ConfirmationMessage
{
    public const STATUS_REMOVED = 'removed';
    public const STATUS_FAILED = 'failed';
    public const DEFAULT_OWNER_APP = 'test-runner';

    public function __construct(
        private string $tenantId,
        private string $dataSubjectRawId,
        private string $policyId,
        private string $policyVersion,
        private ?string $ownerApp = self::DEFAULT_OWNER_APP,
    ) {
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getOwnerApp(): string
    {
        return $this->ownerApp ?? self::DEFAULT_OWNER_APP;
    }

    public function getDataSubjectRawId(): string
    {
        return $this->dataSubjectRawId;
    }

    public function getDataSubjectId(): string
    {
        return $this->dataSubjectRawId;
    }

    public function getPolicyId(): string
    {
        return $this->policyId;
    }

    public function getPolicyVersion(): string
    {
        return $this->policyVersion;
    }
}
