<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Message\Delivery;

use App\Messenger\Message\Delivery\DeliveryLanguageAttachmentMessage;
use PHPUnit\Framework\TestCase;

class DeliveryLanguageAttachmentMessageTest extends TestCase
{
    private DeliveryLanguageAttachmentMessage $message;

    protected function setUp(): void
    {
        $this->message = new DeliveryLanguageAttachmentMessage(
            'deliveryId',
            'en-US',
            '/path/to/package',
            'package-ref',
        );
    }

    public function testItCanRetrieveDeliveryId(): void
    {
        $this->assertEquals('deliveryId', $this->message->getDeliveryId());
    }

    public function testItCanRetrieveLocale(): void
    {
        $this->assertEquals('en-US', $this->message->getLocale());
    }

    public function testItCanRetrievePackagePath(): void
    {
        $this->assertEquals('/path/to/package', $this->message->getPackagePath());
    }

    public function testItCanRetrievePackageRef(): void
    {
        $this->assertEquals('package-ref', $this->message->getPackageRef());
    }
}
