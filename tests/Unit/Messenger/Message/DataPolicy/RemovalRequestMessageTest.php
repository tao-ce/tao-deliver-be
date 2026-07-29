<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Message\DataPolicy;

use App\Messenger\Message\DataPolicy\RemovalRequestMessage;
use App\Validator\Exception\RequestValidationException;
use PHPUnit\Framework\TestCase;

class RemovalRequestMessageTest extends TestCase
{
    public function testFromArrayBuildsMessage(): void
    {
        $raw = [
            'body' => json_encode([
                'policyId' => 'policy-1',
                'policyVersion' => '1',
                'ownerApp' => 'deliver',
                'dataSubjectRawId' => 'subject-1',
                'tenantId' => 'tenant-1',
                'uniqueId' => 'unique-1',
                'storageType' => 'bigtable',
                'name' => 'test',
                'metadata' => [
                    'deliveryExecutions' => ['de-1', 'de-2'],
                ],
            ], JSON_THROW_ON_ERROR),
            'headers' => [
                'type' => 'removal.request',
            ],
        ];

        $message = RemovalRequestMessage::fromArray($raw);

        self::assertSame('removal.request', $message->type);
        self::assertSame('policy-1', $message->policyId);
        self::assertSame('1', $message->policyVersion);
        self::assertSame('subject-1', $message->userId);
        self::assertSame('tenant-1', $message->tenantId);
        self::assertSame('unique-1', $message->uniqueId);
        self::assertSame('bigtable', $message->storageType);
        self::assertSame('deliver', $message->ownerApp);
        self::assertSame('test', $message->name);
        self::assertSame(['de-1', 'de-2'], $message->deliveryExecutionIds);
    }

    public function testFromArrayThrowsOnInvalidPayload(): void
    {
        $this->expectException(RequestValidationException::class);

        RemovalRequestMessage::fromArray([
            'body' => json_encode([
                'policyId' => 'policy-1',
                'policyVersion' => '1',
                'ownerApp' => 'deliver',
                'dataSubjectRawId' => 'subject-1',
                'tenantId' => 'tenant-1',
                'uniqueId' => 'unique-1',
                'storageType' => 'bigtable',
                'name' => 'test',
                'metadata' => [
                    'deliveryExecutions' => [''],
                ],
            ], JSON_THROW_ON_ERROR),
            'headers' => [
                'type' => 'removal.request',
            ],
        ]);
    }
}
