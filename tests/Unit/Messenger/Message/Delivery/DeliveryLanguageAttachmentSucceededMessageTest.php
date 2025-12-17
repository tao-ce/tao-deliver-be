<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Message\Delivery;

use App\Messenger\Message\Delivery\DeliveryLanguageAttachmentSucceededMessage;
use PHPUnit\Framework\TestCase;

class DeliveryLanguageAttachmentSucceededMessageTest extends TestCase
{
    private DeliveryLanguageAttachmentSucceededMessage $message;

    protected function setUp(): void
    {
        $this->message = new DeliveryLanguageAttachmentSucceededMessage(
            'deliveryId',
            'tenantId',
            [
                'locale' => 'en-US',
            ],
        );
    }

    public function testItCanRetrieveDeliveryId(): void
    {
        $this->assertEquals('deliveryId', $this->message->getDeliveryId());
    }

    public function testItCanRetrieveTenantId(): void
    {
        $this->assertEquals('tenantId', $this->message->getTenantId());
    }

    public function testItCanRetrieveConfiguration(): void
    {
        $this->assertEquals(
            [
                'locale' => 'en-US',
            ],
            $this->message->getConfiguration(),
        );
    }
}
