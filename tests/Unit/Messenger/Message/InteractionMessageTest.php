<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Message;

use App\Messenger\Message\InteractionMessage;
use PHPUnit\Framework\TestCase;

class InteractionMessageTest extends TestCase
{
    private InteractionMessage $subject;

    protected function setUp(): void
    {
        $this->subject = $this->createInteractionMessage();
    }

    public function testItCanGetVersion(): void
    {
        $this->assertEquals('0.2.0', $this->subject->getVersion());
    }

    public function testItCanGetPayload(): void
    {
        $this->assertEquals(
            [
                'position' => [
                    'item' => 0,
                    'total' => 6,
                    'part' => 1,
                    'informationalIndex' => 1,
                ],
                'questions' => 1,
                'questionsViewed' => 2,
                'answered' => 3,
                'flagged' => 4,
                'viewed' => 5,
                'tenantId' => 'tenantId',
                'progressPercentage' => 33.33333333333333,
                'durationInSeconds' => 123,
                'deliveryExecution' => [
                    'id' => 'deliveryExecutionId',
                    'startTs' => '1973-11-29T21:33:09+00:00',
                    'endTs' => '1973-11-29T21:33:10+00:00',
                    'delivery' => [
                        'id' => 'deliveryId',
                    ],
                    'status' => 'progress',
                ],
                'user' => [
                    'id' => 'userId',
                    'name' => 'userName',
                ],
                'ipAddress' => '127.0.0.1',
                'title' => 'test title',
                'positionDetails' => null,
                'isTimerExists' => null,
                'battery' => null,
                'locale' => 'en-US',
            ],
            $this->subject->getPayload(),
        );
    }

    public function testPayloadCanContainBatteryInfo(): void
    {
        $payload = $this->createInteractionMessage('batteryId')
            ->getPayload();

        $this->assertTrue(isset($payload['battery']));
        $this->assertEquals(['id' => 'batteryId', 'name' => 'batteryName'], $payload['battery']);
    }

    private function createInteractionMessage(?string $batteryId = null): InteractionMessage
    {
        return new InteractionMessage(
            'deliveryExecutionId',
            'deliveryId',
            'tenantId',
            123456789,
            123,
            '127.0.0.1',
            [
                'item' => 0,
                'total' => 6,
                'part' => 1,
                'informationalIndex' => 1,
            ],
            33.33333333333333,
            'test title',
            1,
            2,
            3,
            4,
            5,
            123456790,
            'userId',
            'userName',
            'progress',
            batteryId: $batteryId,
            batteryName: 'batteryName',
            locale: 'en-US',
        );
    }
}
