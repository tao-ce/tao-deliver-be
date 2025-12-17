<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Domain\DeliveryExecution\Helper;

use App\Domain\DeliveryExecution\Helper\DeliveryExecutionKeyHelper;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionKeyInfo;
use PHPUnit\Framework\TestCase;

class DeliveryExecutionKeyHelperTest extends TestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testDeliveryExecutionKeyInfoCreation(
        ?DeliveryExecutionKeyInfo $expected,
        string $deliveryExecutionKey,
    ): void {
        $this->assertEquals(
            $expected,
            DeliveryExecutionKeyHelper::createDeliveryExecutionKeyInfo($deliveryExecutionKey),
        );
    }

    public function dataProvider(): array
    {
        return [
            [
                new DeliveryExecutionKeyInfo(
                    null,
                    'dIresu',
                    'deliveryId',
                    'resultIdHash',
                    'tenantId',
                ),
                'dIresu#deliveryId#resultIdHash#tenantId',
            ],
            [
                new DeliveryExecutionKeyInfo(
                    'review',
                    'dIresu',
                    'deliveryId',
                    'resultIdHash',
                    'tenantId',
                ),
                'review#dIresu#deliveryId#resultIdHash#tenantId',
            ],
            [
                null,
                '#deliveryId#resultIdHash#tenantId',
            ],
            [
                null,
                '#resultIdHash#tenantId',
            ],
            [
                null,
                '#tenantId',
            ],
            [
                null,
                '#',
            ],
            [
                null,
                '',
            ],
        ];
    }
}
