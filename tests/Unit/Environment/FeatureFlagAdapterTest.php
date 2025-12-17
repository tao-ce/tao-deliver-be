<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Environment;

use OAT\Library\EnvironmentManagementClient\Exception\FeatureFlagNotFoundException;
use OAT\Library\EnvironmentManagementClient\Exception\GrpcCallFailedException;
use OAT\Library\EnvironmentManagementClient\Model\FeatureFlag;
use PHPUnit\Framework\TestCase;
use App\Environment\FeatureFlagAdapter;
use OAT\Library\EnvironmentManagementClient\Repository\FeatureFlagRepositoryInterface;
use Exception;

class FeatureFlagAdapterTest extends TestCase
{
    private const EXPECTED_NOT_FOUND_CODE = 5;

    private FeatureFlagAdapter $subject;
    private FeatureFlagRepositoryInterface $flagRepository;

    protected function setUp(): void
    {
        $this->flagRepository = $this->createMock(FeatureFlagRepositoryInterface::class);
        $this->subject = new FeatureFlagAdapter($this->flagRepository);
    }

    public function testParseFalse(): void
    {
        $this->createMockResponse('0');
        $this->assertFalse(
            $this->subject->isEnabled('test', 'flag'),
        );

        $this->createMockResponse('false');
        $this->assertFalse(
            $this->subject->isEnabled('test', 'flag'),
        );
    }

    public function testParseTrue(): void
    {
        $this->createMockResponse('1');
        $this->assertTrue(
            $this->subject->isEnabled('test', 'flag'),
        );

        $this->createMockResponse('true');
        $this->assertTrue(
            $this->subject->isEnabled('test', 'flag'),
        );
    }

    public function testIsEnabledReturnsFalseWhenFlagNotFound(): void
    {
        $this->flagRepository
            ->method('find')
            ->with('test', 'flag')
            ->willThrowException(new FeatureFlagNotFoundException());

        $this->assertFalse(
            $this->subject->isEnabled('test', 'flag'),
        );
    }

    public function testIsEnabledReturnsDefaultValueWhenFlagNotFound(): void
    {
        $this->flagRepository
            ->method('find')
            ->with('test', 'flag')
            ->willThrowException(new FeatureFlagNotFoundException());

        $this->assertTrue(
            $this->subject->isEnabled('test', 'flag', true),
        );
    }

    private function createMockResponse(bool|string $value): void
    {
        $featureFlagMock = $this->createMock(FeatureFlag::class);
        $featureFlagMock->method('getValue')->willReturn($value);

        $this->flagRepository->method('find')->willReturn($featureFlagMock);
    }
}
