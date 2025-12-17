<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Domain\DeliveryExecution\Model;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionKeyInfo;
use PHPUnit\Framework\TestCase;

class DeliveryExecutionKeyInfoTest extends TestCase
{
    public function testIsSnapshotReturnsTrueWhenAttemptIdSpecified(): void
    {
        $this->assertTrue(
            $this->createDeliveryExecutionKeyInfo()
                ->withAttempt(1)
                ->isSnapshot(),
        );
    }

    public function testSnapshotIdIsUnique(): void
    {
        $this->assertNotEquals(
            (string)$this->createDeliveryExecutionKeyInfo(resultIdHash: 'session-1')->withAttempt(1),
            (string)$this->createDeliveryExecutionKeyInfo(resultIdHash: 'session-2')->withAttempt(1),
        );
    }

    public function testSnapshotIdIsPreservedOnMultipleAttempts(): void
    {
        $this->assertEquals(
            (string)$this->createDeliveryExecutionKeyInfo(resultIdHash: 'session-1')->withAttempt(2),
            (string)$this->createDeliveryExecutionKeyInfo(resultIdHash: 'session-1')->withAttempt(1)->withAttempt(2),
        );
    }

    public function testResultIdIsPreservedOnAnAttempt(): void
    {
        $this->assertStringContainsString(
            '1-session#',
            (string)$this->createDeliveryExecutionKeyInfo(resultIdHash: '1-session')->withAttempt(1)->withAttempt(2),
        );
    }

    public function testIsSnapshotReturnsFalseWhenNoAttemptIdSpecified(): void
    {
        $this->assertFalse(
            $this->createDeliveryExecutionKeyInfo()
                ->isSnapshot(),
        );
    }

    public function testIsReviewReturnsTrueWhenModeSpecified(): void
    {
        $this->assertTrue(
            $this->createDeliveryExecutionKeyInfo(mode: 'review')
                ->isReview(),
        );
    }
    public function testIsReviewReturnsFalseWhenAttemptIdSpecified(): void
    {
        $this->assertFalse(
            $this->createDeliveryExecutionKeyInfo(mode: '1')
                ->isReview(),
        );
    }

    public function testIsReviewReturnsFalseWhenUnknownModeSpecified(): void
    {
        $this->assertFalse(
            $this->createDeliveryExecutionKeyInfo(mode: 'unknown')
                ->isReview(),
        );
    }

    public function testIsReviewReturnsFalseWhenNoModeSpecified(): void
    {
        $this->assertFalse(
            $this->createDeliveryExecutionKeyInfo()
                ->isReview(),
        );
    }

    public function testUserIdReturnedWhenDefined(): void
    {
        $userId = 'someUserId';
        $this->assertSame(
            strrev($userId),
            $this->createDeliveryExecutionKeyInfo(userId: $userId)
                ->getUserId(),
        );
    }

    public function testUserIdIsNullWhenAnonymous(): void
    {
        $this->assertNull(
            $this->createDeliveryExecutionKeyInfo(userId: strrev('anonymous-some-hash'))
                ->getUserId(),
        );
    }

    public function testDeliveryIdReturned(): void
    {
        $deliveryId = 'someDeliveryId';
        $this->assertSame(
            $deliveryId,
            $this->createDeliveryExecutionKeyInfo(deliveryId: $deliveryId)
                ->getDeliveryId(),
        );
    }

    public function testDryRunIsFalseByDefault(): void
    {
        $this->assertFalse(
            $this->createDeliveryExecutionKeyInfo()
                ->isDryRun(),
        );
    }

    public function testDryRunIsTrueWhenResultIdIsSet(): void
    {
        $this->assertTrue(
            $this->createDeliveryExecutionKeyInfo(resultIdHash: sha1(DeliveryExecution::DRY_RUN_ATTEMPT_ID))
                ->isDryRun(),
        );
    }

    public function testTenantIdReturned(): void
    {
        $tenantId = 'someTenantId';
        $this->assertSame(
            $tenantId,
            $this->createDeliveryExecutionKeyInfo(tenantId: $tenantId)
                ->getTenantId(),
        );
    }

    private function createDeliveryExecutionKeyInfo(
        ?string $mode = null,
        string $userId = 'userId',
        string $deliveryId = 'deliveryId',
        string $resultIdHash = 'resultIdHash',
        string $tenantId = 'tenantId',
    ): DeliveryExecutionKeyInfo {
        return new DeliveryExecutionKeyInfo(
            $mode,
            $userId,
            $deliveryId,
            $resultIdHash,
            $tenantId,
        );
    }
}
