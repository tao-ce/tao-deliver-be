<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\Ags;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Service\Ags\AgsInitializationService;
use App\Service\Lti\LtiAgsScoreService;
use App\Tests\Traits\LoggerTestingTrait;
use Carbon\Carbon;
use DateTimeImmutable;
use Monolog\Logger;
use OAT\Library\EnvironmentManagementLtiClient\Exception\LtiAgsClientException;
use OAT\Library\Lti1p3Ags\Model\Score\ScoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AgsInitializationServiceTest extends KernelTestCase
{
    use LoggerTestingTrait;

    private LtiAgsScoreService|MockObject $ltiAgsScoreServiceMock;
    private DeliveryExecution|MockObject $deliveryExecutionMock;
    private AgsInitializationService $sut;

    protected function setUp(): void
    {
        parent::setUp();
        static::bootKernel();

        $this->ltiAgsScoreServiceMock = $this->createMock(LtiAgsScoreService::class);
        $this->deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $this->deliveryExecutionMock->method('getStartedAt')->willReturn(
            new DateTimeImmutable(Carbon::now()->toISOString()),
        );
        $this->setUpTestLogHandler();

        $this->sut = new AgsInitializationService(
            $this->ltiAgsScoreServiceMock,
            static::getContainer()->get('monolog.logger.audit_delivery_execution'),
        );
    }

    public function testSuccessfulAgsInitialization(): void
    {
        $executionId = Uuid::uuid4()->toString();
        $this->deliveryExecutionMock->expects(self::once())->method('getId')->willReturn($executionId);
        $this->ltiAgsScoreServiceMock->expects(self::once())->method('send')->willReturn(true);

        $this->sut->init($this->deliveryExecutionMock);

        $this->assertHasLogRecordWithMessage(
            sprintf(
                '[%s] - LTI score published with status [%s]',
                $executionId,
                ScoreInterface::GRADING_PROGRESS_STATUS_NOT_READY,
            ),
            Logger::INFO,
            'audit_delivery_execution',
        );
    }

    public function testFailedAgsInitialization(): void
    {
        $exception = new LtiAgsClientException();
        $this->ltiAgsScoreServiceMock->expects(self::once())->method('send')->willThrowException($exception);
        $this->expectExceptionObject($exception);

        $this->sut->init($this->deliveryExecutionMock);
    }

    public function testAgsInitializationWithNotWritableScope(): void
    {
        $executionId = Uuid::uuid4()->toString();
        $this->deliveryExecutionMock->method('getId')->willReturn($executionId);
        $this->ltiAgsScoreServiceMock->expects(self::once())->method('send')->willReturn(false);

        $this->sut->init($this->deliveryExecutionMock);

        $this->assertHasNoLogRecordWithMessage(
            sprintf(
                '[%s] - LTI score published with status [%s]',
                $executionId,
                ScoreInterface::GRADING_PROGRESS_STATUS_NOT_READY,
            ),
            Logger::INFO,
        );
    }
}
