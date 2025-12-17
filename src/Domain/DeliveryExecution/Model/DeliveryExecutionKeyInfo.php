<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model;

use App\Domain\Delivery\Model\Delivery;
use App\Domain\Tenant\Model\TenantAwareInterface;
use App\Qti\Compiler\QtiPackageCompiler;
use App\Traits\FilesystemTrait;

class DeliveryExecutionKeyInfo implements TenantAwareInterface
{
    use FilesystemTrait;

    private const SNAPSHOT_ATTEMPT_ID = 'snapshot';

    private ?string $userId;
    private string $originalUserId;

    public function __construct(
        private ?string $mode,
        string $userId,
        private string $deliveryId,
        private string $attemptIdHash,
        private string $tenantId,
    ) {
        $this->originalUserId = $userId;
        // Check if user id was encoded from URL format (only for TAO 3.x cases with guest users)
        $userId = urldecode(strrev($userId));
        $this->userId = str_starts_with($userId, 'anonymous') ? null : $userId;
    }

    public function __toString(): string
    {
        return implode(
            DeliveryExecution::DOCUMENT_KEY_DELIMITER,
            [
                $this->originalUserId,
                $this->deliveryId,
                $this->attemptIdHash,
                $this->tenantId,
            ],
        );
    }

    public function getCompactTestXmlPath(?string $locale = null): string
    {
        return $this->getAssetPath(QtiPackageCompiler::COMPACT_TEST_FILE_NAME, locale: $locale);
    }

    public function getItemDataPath(string $id, ?string $locale = null): string
    {
        return $this->getAssetPath(QtiPackageCompiler::JSON_ITEM_FILE_NAME, $id, $locale);
    }

    public function getPortableItemDataPath(string $id, ?string $locale = null): string
    {
        return $this->getAssetPath(QtiPackageCompiler::JSON_ITEM_PORTABLE_ELEMENTS_FILE_NAME, $id, $locale);
    }

    public function getVariableElementsPath(string $id, ?string $locale = null): string
    {
        return $this->getAssetPath(QtiPackageCompiler::JSON_ITEM_VARIABLE_ELEMENTS_FILE_NAME, $id, $locale);
    }

    public function getOriginalId(?string $attemptId = null): string
    {
        return (string)($this->isSnapshot()
            ? $this->withAttemptIdHash(sha1($attemptId ?? DeliveryExecution::ATTEMPT_ID))
            : $this);
    }

    public function getAssetPath(string $assetName, ?string $itemId = null, ?string $locale = null): string
    {
        $pathParts = [];

        if ($this->isUnlistedReview()) {
            $pathParts[] = $this->getTenantId();
        }

        $pathParts[] = $this->getDeliveryId();

        if ($locale) {
            $pathParts[] = Delivery::LOCALE_FOLDER_NAME;
            $pathParts[] = $locale;
        }

        if ($itemId !== null) {
            $pathParts[] = $itemId;
        }

        $pathParts[] = $assetName;

        return $this->buildPathFor(...$pathParts);
    }

    public function isSnapshot(): bool
    {
        return str_starts_with($this->attemptIdHash, sha1(self::SNAPSHOT_ATTEMPT_ID));
    }

    public function isReview(): bool
    {
        return $this->mode === DeliveryExecution::REVIEW_MODE_PREFIX
            || $this->isUnlistedReview();
    }

    public function isUnlistedReview(): bool
    {
        return $this->mode === DeliveryExecution::UNLISTED_REVIEW_MODE_PREFIX;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getOriginalUserId(): ?string
    {
        return $this->originalUserId;
    }

    public function getDeliveryId(): string
    {
        return $this->deliveryId;
    }

    public function isDryRun(): bool
    {
        return $this->attemptIdHash === sha1(DeliveryExecution::DRY_RUN_ATTEMPT_ID);
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function withAttempt(int $attempt): self
    {
        $attemptIdHash = sha1(self::SNAPSHOT_ATTEMPT_ID);
        return $this->withAttemptIdHash(
            $attemptIdHash
            . $attempt
            . preg_replace("/^(?:$attemptIdHash\d+\.)?(.+)$/", '.$1', $this->attemptIdHash),
        );
    }

    private function withAttemptIdHash(string $attemptIdHash): self
    {
        $that = clone $this;
        $that->attemptIdHash = $attemptIdHash;

        return $that;
    }
}
