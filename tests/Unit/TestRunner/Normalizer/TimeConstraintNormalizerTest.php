<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\Normalizer;

use App\TestRunner\Normalizer\TimeConstraintNormalizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use qtism\common\datatypes\QtiDuration;
use qtism\data\AssessmentTest;
use qtism\data\TestPart;
use qtism\data\TimeLimits;
use qtism\runtime\tests\AssessmentTestSession;
use qtism\runtime\tests\TimeConstraint;
use qtism\runtime\tests\TimeConstraintCollection;

class TimeConstraintNormalizerTest extends TestCase
{
    /** @var TimeConstraintNormalizer */
    private $subject;

    /** @var AssessmentTestSession|MockObject */
    private $testSessionMock;

    /** @var TimeConstraint|MockObject */
    private $timeConstraintMock;

    /** @var TimeLimits|MockObject */
    private $timeLimitsMock;

    protected function setUp(): void
    {
        $this->testSessionMock = $this->createMock(AssessmentTestSession::class);
        $this->timeConstraintMock = $this->createMock(TimeConstraint::class);
        $this->timeLimitsMock = $this->createMock(TimeLimits::class);

        $this->subject = new TimeConstraintNormalizer();
    }

    public function testNormalizeWithMinAndMaxTime(): void
    {
        $this->setupTimeLimitsMock();
        $this->setupTimeConstraintMock();

        $this->testSessionMock
            ->method('isRunning')
            ->willReturn(true);

        $this->assertEquals([
            'allowLateSubmission' => true,
            'label' => 'title',
            'maxTime' => 12345,
            'maxTimeRemaining' => 12345,
            'minTime' => 12345,
            'minTimeRemaining' => 12345,
            'qtiClassName' => 'qtiClassName',
            'source' => 'identifier',
            'extraTime' => [
                'total' => 0,
                'consumed' => 0,
                'remaining' => 0,
            ],
        ], $this->subject->normalize($this->testSessionMock, $this->timeConstraintMock));
    }

    public function testNormalizeWithoutMinAndMaxTime(): void
    {
        $this->setupTimeConstraintMock(true, false, false);
        $this->setupTimeLimitsMock(false, false);

        $assessmentTestMock = $this->createMock(AssessmentTest::class);

        $this->testSessionMock
            ->method('getAssessmentTest')
            ->willReturn($assessmentTestMock);

        $this->testSessionMock
            ->method('isRunning')
            ->willReturn(true);

        $assessmentTestMock
            ->method('getTimeLimits')
            ->willReturn($this->timeLimitsMock);

        $this->assertEquals([
            'allowLateSubmission' => true,
            'label' => 'title',
            'maxTime' => false,
            'maxTimeRemaining' => false,
            'minTime' => false,
            'minTimeRemaining' => false,
            'qtiClassName' => 'qtiClassName',
            'source' => 'identifier',
            'extraTime' => [
                'total' => 0,
                'consumed' => 0,
                'remaining' => 0,
            ],
        ], $this->subject->normalize($this->testSessionMock, $this->timeConstraintMock));
    }

    public function testNormalizeWithoutTitle(): void
    {
        $this->setupTimeConstraintMock(false);
        $this->setupTimeLimitsMock();

        $assessmentTestMock = $this->createMock(AssessmentTest::class);

        $this->testSessionMock
            ->method('getAssessmentTest')
            ->willReturn($assessmentTestMock);

        $this->testSessionMock
            ->method('isRunning')
            ->willReturn(true);

        $assessmentTestMock
            ->method('getTimeLimits')
            ->willReturn($this->timeLimitsMock);

        $this->assertEquals([
            'allowLateSubmission' => true,
            'label' => 'identifier',
            'maxTime' => 12345,
            'maxTimeRemaining' => 12345,
            'minTime' => 12345,
            'minTimeRemaining' => 12345,
            'qtiClassName' => 'qtiClassName',
            'source' => 'identifier',
            'extraTime' => [
                'total' => 0,
                'consumed' => 0,
                'remaining' => 0,
            ],
        ], $this->subject->normalize($this->testSessionMock, $this->timeConstraintMock));
    }

    public function testNormalizeIfSessionNotRunning(): void
    {
        $this->setupTimeConstraintMock();
        $this->setupTimeLimitsMock();

        $this->testSessionMock
            ->method('isRunning')
            ->willReturn(false);

        $this->assertEquals(null, $this->subject->normalize($this->testSessionMock, $this->timeConstraintMock));
    }

    public function testNormalizeCollectionIfTimeLimitsAreNull(): void
    {
        $this->setupTimeConstraintMock();
        $this->setupTimeLimitsMock();

        $assessmentTestMock = $this->createMock(AssessmentTest::class);

        $this->testSessionMock
            ->method('getAssessmentTest')
            ->willReturn($assessmentTestMock);

        $this->testSessionMock
            ->method('isRunning')
            ->willReturn(true);

        $assessmentTestMock
            ->method('getTimeLimits')
            ->willReturn(null);

        $this->assertEquals([], $this->subject->normalizeCollection($this->testSessionMock));
    }

    public function testNormalizeCollectionWithEmptyCollectionParameter(): void
    {
        $assessmentTestMock = $this->createMock(AssessmentTest::class);

        $this->testSessionMock
            ->method('getAssessmentTest')
            ->willReturn($assessmentTestMock);

        $this->testSessionMock
            ->method('isRunning')
            ->willReturn(true);

        $assessmentTestMock
            ->method('getTimeLimits')
            ->willReturn($this->timeLimitsMock);

        $this->assertEquals(
            [],
            $this->subject->normalizeCollection(
                $this->testSessionMock,
                new TimeConstraintCollection([]),
            ),
        );
    }

    public function testNormalizeCollectionWithNonEmptyCollectionParameter(): void
    {
        $this->setupTimeConstraintMock();
        $this->setupTimeLimitsMock();

        $assessmentTestMock = $this->createMock(AssessmentTest::class);

        $this->testSessionMock
            ->method('getAssessmentTest')
            ->willReturn($assessmentTestMock);

        $this->testSessionMock
            ->method('isRunning')
            ->willReturn(true);

        $assessmentTestMock
            ->method('getTimeLimits')
            ->willReturn($this->timeLimitsMock);

        $this->assertEquals(
            [
                0 => [
                    'allowLateSubmission' => true,
                    'label' => 'title',
                    'maxTime' => 12345,
                    'maxTimeRemaining' => 12345,
                    'minTime' => 12345,
                    'minTimeRemaining' => 12345,
                    'qtiClassName' => 'qtiClassName',
                    'source' => 'identifier',
                    'extraTime' => [
                        'total' => 0,
                        'consumed' => 0,
                        'remaining' => 0,
                    ],
                ],
            ],
            $this->subject->normalizeCollection(
                $this->testSessionMock,
                new TimeConstraintCollection([$this->timeConstraintMock]),
            ),
        );
    }

    public function testNormalizeCollectionWithoutCollectionParameter(): void
    {
        $this->setupTimeConstraintMock();
        $this->setupTimeLimitsMock();

        $assessmentTestMock = $this->createMock(AssessmentTest::class);

        $this->testSessionMock
            ->method('getAssessmentTest')
            ->willReturn($assessmentTestMock);

        $this->testSessionMock
            ->method('isRunning')
            ->willReturn(true);

        $this->testSessionMock
            ->method('getTimeConstraints')
            ->willReturn(new TimeConstraintCollection([$this->timeConstraintMock]));

        $assessmentTestMock
            ->method('getTimeLimits')
            ->willReturn($this->timeLimitsMock);

        $this->assertEquals(
            [
                0 => [
                    'allowLateSubmission' => true,
                    'label' => 'title',
                    'maxTime' => 12345,
                    'maxTimeRemaining' => 12345,
                    'minTime' => 12345,
                    'minTimeRemaining' => 12345,
                    'qtiClassName' => 'qtiClassName',
                    'source' => 'identifier',
                    'extraTime' => [
                        'total' => 0,
                        'consumed' => 0,
                        'remaining' => 0,
                    ],
                ],
            ],
            $this->subject->normalizeCollection($this->testSessionMock),
        );
    }

    public function testNormalizeCollectionIfSessionNotRunning(): void
    {
        $this->setupTimeConstraintMock();
        $this->setupTimeLimitsMock();

        $this->testSessionMock
            ->method('isRunning')
            ->willReturn(false);

        $this->assertEquals([], $this->subject->normalizeCollection($this->testSessionMock));
    }

    private function setupTimeLimitsMock(
        bool $withMinTime = true,
        bool $withMaxTime = true,
    ): void {
        $this->timeLimitsMock->method('hasMinTime')->willReturn($withMinTime);
        $this->timeLimitsMock->method('hasMaxTime')->willReturn($withMaxTime);
        $this->timeLimitsMock->method('getMinTime')->willReturn($this->getQtiDurationMock());
        $this->timeLimitsMock->method('getMaxTime')->willReturn($this->getQtiDurationMock());
    }

    private function setupTimeConstraintMock(
        bool $withTitle = true,
        bool $withMaximumRemainingTime = true,
        bool $withMinimumRemainingTime = true,
    ): void {
        if ($withTitle) {
            $qtiComponentMock = $this->createMock(AssessmentTest::class);

            $qtiComponentMock->method('getTitle')->willReturn('title');
        } else {
            $qtiComponentMock = $this->createMock(TestPart::class);
        }

        $qtiComponentMock->method('getIdentifier')->willReturn('identifier');
        $qtiComponentMock->method('getQtiClassName')->willReturn('qtiClassName');
        $qtiComponentMock->method('getTimeLimits')->willReturn($this->timeLimitsMock);

        $this->timeConstraintMock->method('getSource')->willReturn($qtiComponentMock);
        $this->timeConstraintMock->method('allowLateSubmission')->willReturn(true);

        if ($withMaximumRemainingTime) {
            $this->timeConstraintMock->method('getMaximumRemainingTime')->willReturn($this->getQtiDurationMock());
        } else {
            $this->timeConstraintMock->method('getMaximumRemainingTime')->willReturn(false);
        }

        if ($withMinimumRemainingTime) {
            $this->timeConstraintMock->method('getMinimumRemainingTime')->willReturn($this->getQtiDurationMock());
        } else {
            $this->timeConstraintMock->method('getMinimumRemainingTime')->willReturn(false);
        }
    }

    private function getQtiDurationMock(): QtiDuration
    {
        $qtiDurationMock = $this->createMock(QtiDuration::class);

        $qtiDurationMock
            ->method('getSeconds')
            ->willReturn(12345);

        return $qtiDurationMock;
    }
}
