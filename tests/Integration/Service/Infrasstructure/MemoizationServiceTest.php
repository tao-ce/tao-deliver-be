<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

use App\Repository\DeliveryExecutionRepository;
use App\Service\Battery\BatteryService;
use App\Service\DeliveryExecution\DeliveryExecutionPropertyService;
use App\Service\Infrastructure\Contract\MemoizedService;
use App\Service\Infrastructure\MemoizationService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MemoizationServiceTest extends KernelTestCase
{
    private MemoizationService $sut;

    public function setUp(): void
    {
        $this->sut = static::getContainer()->get(MemoizationService::class);
    }

    public function testAllRequiredServicesFlush(): void
    {
        $sutReflection = new ReflectionClass($this->sut);
        $expectedMemoizedServices = [
            DeliveryExecutionRepository::class => true,
            BatteryService::class => true,
            DeliveryExecutionPropertyService::class => true,
        ];
        foreach ($sutReflection->getProperty('memoizedServices')->getValue($this->sut) as $memoizedService) {
            $this->assertArrayHasKey($memoizedService::class, $expectedMemoizedServices);
            unset($expectedMemoizedServices[$memoizedService::class]);
        }
        $this->assertEmpty($expectedMemoizedServices, 'Some services require ' . MemoizedService::class);
    }
}
