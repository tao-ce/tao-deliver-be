<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\Enrollment\EnrollmentService;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Repository\EnrollmentRepository;
use App\Domain\Tenant\Model\PortalSettingsRepositoryInterface;
use App\Domain\Enrollment\Model\Enrollment;
use PHPUnit\Framework\MockObject\MockObject;

class EnrollmentServiceTest extends TestCase
{
    private EnrollmentService $subject;

    private EnrollmentRepository|MockObject $enrollmentRepository;

    private PortalSettingsRepositoryInterface|MockObject $portalSettingsRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enrollmentRepository = $this->createMock(EnrollmentRepository::class);
        $this->portalSettingsRepository = $this->createMock(PortalSettingsRepositoryInterface::class);

        $this->subject = new EnrollmentService(
            $this->enrollmentRepository,
            $this->portalSettingsRepository,
        );
    }

    public function testGetSessionDataByDeliveryExecutionWithValidEnrollment(): void
    {
        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $enrollment = $this->createMock(Enrollment::class);

        $enrollment->method('getCampaignId')->willReturn('campaign_1');
        $enrollment->method('getCampaignName')->willReturn('Campaign One');
        $enrollment->method('getSessionName')->willReturn('Session One');
        $enrollment->method('getSessionId')->willReturn('sessionId');
        $enrollment->method('getSessionTemplateName')->willReturn('My super template');
        $enrollment->method('getSessionTemplateId')->willReturn('templateId');
        $enrollment->method('getTestCategory')->willReturn(['category_1', 'category_2']);

        $this->enrollmentRepository
            ->method('findSession')
            ->with($deliveryExecution)
            ->willReturn($enrollment);

        $this->portalSettingsRepository
            ->method('findTestCategories')
            ->with($deliveryExecution)
            ->willReturn([
                'category_1' => 'Math',
                'category_2' => 'Science',
            ]);

        $result = $this->subject->getSessionDataByDeliveryExecution($deliveryExecution);

        $this->assertEquals([
            'campaignId' => 'campaign_1',
            'campaignName' => 'Campaign One',
            'sessionId' => 'sessionId',
            'sessionName' => 'Session One',
            'sessionTemplateId' => 'templateId',
            'sessionTemplateName' => 'My super template',
            'enrollmentTestCategories' => ['Math', 'Science'],
        ], $result);
    }

    public function testGetSessionDataByDeliveryExecutionWithEmptyEnrollment(): void
    {
        $deliveryExecution = $this->createMock(DeliveryExecution::class);

        $this->enrollmentRepository
            ->method('findSession')
            ->with($deliveryExecution)
            ->willReturn(null);

        $result = $this->subject->getSessionDataByDeliveryExecution($deliveryExecution);

        $this->assertEquals(null, $result);
    }

    public function testGetSessionDataByDeliveryExecutionWithMissingTestCategories(): void
    {
        $deliveryExecution = $this->createMock(DeliveryExecution::class);
        $enrollment = $this->createMock(Enrollment::class);

        $enrollment->method('getCampaignId')->willReturn('campaign_1');
        $enrollment->method('getCampaignName')->willReturn('Campaign One');
        $enrollment->method('getSessionName')->willReturn('Session One');
        $enrollment->method('getSessionId')->willReturn('sessionId');
        $enrollment->method('getSessionTemplateName')->willReturn('My super template');
        $enrollment->method('getSessionTemplateId')->willReturn('templateId');
        $enrollment->method('getTestCategory')->willReturn(['category_1', 'category_2']);

        $this->enrollmentRepository
            ->method('findSession')
            ->with($deliveryExecution)
            ->willReturn($enrollment);

        $this->portalSettingsRepository
            ->method('findTestCategories')
            ->with($deliveryExecution)
            ->willReturn([
                'category_1' => 'Math',
            ]);

        $result = $this->subject->getSessionDataByDeliveryExecution($deliveryExecution);

        $this->assertEquals([
            'campaignId' => 'campaign_1',
            'campaignName' => 'Campaign One',
            'sessionId' => 'sessionId',
            'sessionName' => 'Session One',
            'sessionTemplateId' => 'templateId',
            'sessionTemplateName' => 'My super template',
            'enrollmentTestCategories' => ['Math'],
        ], $result);
    }
}
