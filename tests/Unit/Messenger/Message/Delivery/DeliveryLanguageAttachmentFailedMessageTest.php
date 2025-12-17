<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Message\Delivery;

use App\Messenger\Message\Delivery\DeliveryLanguageAttachmentFailedMessage;
use Exception;
use PHPUnit\Framework\TestCase;

class DeliveryLanguageAttachmentFailedMessageTest extends TestCase
{
    private DeliveryLanguageAttachmentFailedMessage $subject;

    public function setUp(): void
    {
        $this->subject = new DeliveryLanguageAttachmentFailedMessage(
            'deliveryId',
            'tenantId',
            [
                'locale' => 'en-US',
            ],
            [
                'en-US' => [
                    'packageRef' => 'package-ref',
                ],
            ],
            [
                [
                    'code' => 500,
                    'exceptionCode' => 501,
                    'type' => Exception::class,
                    'message' => 'Language attachment failed',
                ],
            ],
        );
    }

    public function testItCanRetrieveDeliveryId(): void
    {
        $this->assertEquals('deliveryId', $this->subject->getDeliveryId());
    }

    public function testItCanRetrieveTenantId(): void
    {
        $this->assertEquals('tenantId', $this->subject->getTenantId());
    }

    public function testItCanRetrieveConfiguration(): void
    {
        $this->assertEquals(
            [
                'locale' => 'en-US',
            ],
            $this->subject->getConfiguration(),
        );
    }

    public function testItCanRetrieveTranslations(): void
    {
        $this->assertEquals(
            [
                'en-US' => [
                    'packageRef' => 'package-ref',
                ],
            ],
            $this->subject->getTranslations(),
        );
    }

    public function testItCanRetrieveErrors(): void
    {
        $this->assertEquals(
            [
                [
                    'code' => 500,
                    'exceptionCode' => 501,
                    'type' => Exception::class,
                    'message' => 'Language attachment failed',
                ],
            ],
            $this->subject->getErrors(),
        );
    }
}
