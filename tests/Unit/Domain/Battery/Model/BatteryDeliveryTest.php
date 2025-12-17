<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Battery\Model;

use App\Domain\Battery\Model\BatteryDelivery;
use PHPUnit\Framework\TestCase;

class BatteryDeliveryTest extends TestCase
{
    public function testGetters(): void
    {
        $delivery = new BatteryDelivery('id', 'pass', 1);

        $this->assertSame('id', $delivery->id);
        $this->assertSame('pass', $delivery->password);
        $this->assertSame(1, $delivery->order);
    }

    public function testGettersWithNullFields(): void
    {
        $delivery = new BatteryDelivery('id', null, null);

        $this->assertSame('id', $delivery->id);
        $this->assertNull($delivery->password);
        $this->assertNull($delivery->order);
    }
}
