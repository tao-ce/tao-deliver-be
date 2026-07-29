<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message\DataPolicy;

readonly class RemovalConfirmationMessage extends ConfirmationMessage
{
    public function __construct(
        private string $uniqueId,
        private string $status,
        private array $errors,
        string $tenantId,
        string $dataSubjectRawId,
        string $policyId,
        string $policyVersion,
        private string $name,
        ?string $ownerApp,
        private string $storageType,
    ) {
        parent::__construct($tenantId, $dataSubjectRawId, $policyId, $policyVersion, $ownerApp);
    }

    public static function createRemovalConfirmationMessage(
        RemovalRequestMessage $requestMessage,
        string $status = self::STATUS_REMOVED,
        array $errors = [],
        ?string $storageType = null,
    ): self {
        return new static(
            $requestMessage->uniqueId,
            $status,
            $errors,
            $requestMessage->tenantId,
            $requestMessage->userId,
            $requestMessage->policyId,
            $requestMessage->policyVersion,
            $requestMessage->name,
            $requestMessage->ownerApp,
            $storageType ?? $requestMessage->storageType,
        );
    }

    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    public function getStorageType(): string
    {
        return $this->storageType;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
