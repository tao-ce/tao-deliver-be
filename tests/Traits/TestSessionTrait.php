<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Traits;

use OAT\Bundle\QtiBundle\Accessor\TestSessionAccessor;
use PHPUnit\Framework\MockObject\MockObject;
use qtism\data\AssessmentItemRef;
use qtism\runtime\tests\AssessmentTestSession;

trait TestSessionTrait
{
    public function createTestSession(string $deliveryExecutionId, string $itemId = 'itemId'): AssessmentTestSession|MockObject
    {
        $assessmentItemRefMock = $this->createMock(AssessmentItemRef::class);
        $assessmentItemRefMock
            ->method('getIdentifier')
            ->willReturn($itemId);

        $testSessionMock = $this->createMock(AssessmentTestSession::class);
        $testSessionMock
            ->method('getCurrentAssessmentItemRef')
            ->willReturn($assessmentItemRefMock);

        $testSessionAccessorMock = $this->createMock(TestSessionAccessor::class);
        $testSessionAccessorMock
            ->method('retrieve')
            ->with($deliveryExecutionId)
            ->willReturn($testSessionMock);

        return $testSessionMock;
    }
}
