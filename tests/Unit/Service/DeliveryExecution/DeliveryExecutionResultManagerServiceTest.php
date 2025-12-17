<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\DeliveryExecution;

use App\Qti\Exception\ResultNotFoundException;
use App\Qti\Service\AssessmentResultService;
use App\Service\DeliveryExecution\DeliveryExecutionResultManagerService;
use PHPUnit\Framework\TestCase;

class DeliveryExecutionResultManagerServiceTest extends TestCase
{
    private AssessmentResultService $assessmentResultService;
    private DeliveryExecutionResultManagerService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assessmentResultService = $this->createMock(AssessmentResultService::class);

        $this->subject = new DeliveryExecutionResultManagerService($this->assessmentResultService);
    }

    public function testDropOfExistedAssessmentResult()
    {
        $this->assessmentResultService->expects(self::once())->method('delete');

        $this->subject->dropResults('deliveryExecutionId');
    }

    public function testDropOfExistedAssessmentResultWithNotFoundedXmlAssessmentResult()
    {
        $this->assessmentResultService->expects(self::once())
            ->method('delete')
            ->willThrowException(new ResultNotFoundException());

        $this->subject->dropResults('deliveryExecutionId');
    }
}
