<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Delivery\Event;

use PHPUnit\Framework\TestCase;
use App\Domain\Delivery\Model\Delivery;
use App\Delivery\Event\DeliveryCreatedEvent;

class DeliveryCreatedEventTest extends TestCase
{
    public function testItCanReturnPropertiesAfterConstruction(): void
    {
        $deliveryMock = $this->createMock(Delivery::class);

        $event = new DeliveryCreatedEvent($deliveryMock);
        $this->assertEquals($deliveryMock, $event->getDelivery());
    }
}
