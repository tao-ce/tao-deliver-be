<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\TestRunner\Event;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\TestRunner\Event\TestSessionInteractionEvent;
use PHPUnit\Framework\TestCase;
use qtism\runtime\tests\AssessmentTestSession;

class TestSessionInteractionEventTest extends TestCase
{
    public function testItCanReturnPropertiesAfterConstruction(): void
    {
        $deliveryExecutionMock = $this->createMock(DeliveryExecution::class);
        $testSessionMock = $this->createMock(AssessmentTestSession::class);

        $event = new TestSessionInteractionEvent('trigger', 'name', $deliveryExecutionMock, $testSessionMock);

        $this->assertEquals('trigger', $event->getTriggeredBy());
        $this->assertEquals('name', $event->getAction());
        $this->assertEquals($deliveryExecutionMock, $event->getDeliveryExecution());
        $this->assertEquals($testSessionMock, $event->getTestSession());
        $this->assertNull($event->getTestMap());
    }
}
