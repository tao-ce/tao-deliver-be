<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Messenger\Message\DataPolicy;

use App\Messenger\Message\DataPolicy\ValidationRequestMessage;
use App\Validator\Exception\RequestValidationException;
use PHPUnit\Framework\TestCase;

class ValidationRequestMessageTest extends TestCase
{
    public function testFromArrayBuildsMessage(): void
    {
        $raw = [
            'body' => json_encode([
                'tenantId' => 'tenant-1',
                'dataSubjectRawId' => 'subject-1',
                'policyId' => 'policy-1',
                'policyVersion' => '1',
                'ownerApp' => 'deliver',
            ], JSON_THROW_ON_ERROR),
            'headers' => [
                'type' => 'validation.request',
            ],
        ];

        $message = ValidationRequestMessage::fromArray($raw);

        self::assertSame('validation.request', $message->type);
        self::assertSame('policy-1', $message->policyId);
        self::assertSame('1', $message->policyVersion);
        self::assertSame('tenant-1', $message->tenantId);
        self::assertSame('deliver', $message->ownerApp);
        self::assertSame('subject-1', $message->userId);
    }

    public function testFromArrayThrowsOnInvalidPayload(): void
    {
        $this->expectException(RequestValidationException::class);

        ValidationRequestMessage::fromArray([
            'body' => json_encode([
                'tenantId' => 'tenant-1',
                'dataSubjectRawId' => 'subject-1',
                'policyId' => 'policy-1',
                'policyVersion' => '1',
                # ownerApp is missing
            ], JSON_THROW_ON_ERROR),
            'headers' => [
                'type' => 'validation.request',
            ],
        ]);
    }
}
