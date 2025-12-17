<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Environment\FeatureFlagAdapterInterface;
use App\Service\DeliveryExecution\ScoringEligibilityChecker;
use App\Tests\Traits\DomainTestingTrait;
use OAT\Library\EnvironmentManagementClient\Model\FeatureFlag;
use OAT\Library\EnvironmentManagementClient\Repository\FeatureFlagRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ScoringEligibilityCheckerTest extends TestCase
{
    use DomainTestingTrait;

    /** @var MockObject|FeatureFlagRepositoryInterface */
    private FeatureFlagAdapterInterface $featureFlagAdapter;

    private ScoringEligibilityChecker $sut;

    /**
     * @before
     */
    public function init(): void
    {
        $this->featureFlagAdapter = $this->createMock(FeatureFlagAdapterInterface::class);

        $this->sut = new ScoringEligibilityChecker($this->featureFlagAdapter);
    }

    public function testItPassesOnNonAnonymousIfFeatureEnabled(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution()
            ->setLtiLaunchParameters(['user_id' => 'foo']);

        $this->expectScoringSubmissionFeatureFlag($deliveryExecution, true);

        $this->assertTrue(
            $this->sut->isEligible($deliveryExecution),
        );
    }

    public function testItFailsOnAnonymousIfFeatureEnabled(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution()
            ->setLtiLaunchParameters(['user_id' => 'anonymous-123']);

        $this->expectScoringSubmissionFeatureFlag($deliveryExecution, true);

        $this->assertFalse(
            $this->sut->isEligible($deliveryExecution),
        );
    }

    public function testItFailsIfFeatureDisabled(): void
    {
        $deliveryExecution = $this->createTestDeliveryExecution();

        $this->expectScoringSubmissionFeatureFlag($deliveryExecution, false);

        $this->assertFalse(
            $this->sut->isEligible($deliveryExecution),
        );
    }

    private function expectScoringSubmissionFeatureFlag(DeliveryExecution $deliveryExecution, bool $value): void
    {
        $this->featureFlagAdapter
            ->method('isEnabled')
            ->with($deliveryExecution->getTenantId(), 'SCORING_SUBMISSION_ENABLED')
            ->willReturn($value);
    }
}
