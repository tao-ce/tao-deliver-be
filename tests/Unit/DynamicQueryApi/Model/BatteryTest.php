<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\DynamicQueryApi\Model;

use App\DynamicQueryApi\Model\Battery;
use PHPUnit\Framework\TestCase;

class BatteryTest extends TestCase
{
    public function testGetters(): void
    {
        $battery = new Battery(
            'id',
            'name',
            'description',
            'mode',
            'status',
            'tenantId',
            ['deliveryId1', 'deliveryId2'],
        );

        $this->assertSame('id', $battery->getId());
        $this->assertSame('name', $battery->getName());
        $this->assertSame('description', $battery->getDescription());
        $this->assertSame('mode', $battery->getMode());
        $this->assertSame('status', $battery->getStatus());
        $this->assertSame('tenantId', $battery->getTenantId());
        $this->assertSame(['deliveryId1', 'deliveryId2'], $battery->getDeliveryIds());
    }
}
