<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Validator\Messenger\DataPolicy;

use App\Validator\Exception\RequestValidationException;
use App\Validator\Messenger\DataPolicy\RequestMessageValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RequestMessageValidatorTest extends TestCase
{
    private RequestMessageValidator $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new RequestMessageValidator($this->createValidator());
    }

    public function testValidateAndNormalizeReturnsNormalizedPayload(): void
    {
        $raw = [
            'body' => json_encode([
                'policyId' => 'policy-1',
                'policyVersion' => '1',
                'dataSubjectRawId' => 'subject-1',
                'tenantId' => 'tenant-1',
            ], JSON_THROW_ON_ERROR),
            'headers' => [
                'type' => 'removal.request',
            ],
        ];

        self::assertSame(
            [
                'type' => 'removal.request',
                'body' => [
                    'policyId' => 'policy-1',
                    'policyVersion' => '1',
                    'dataSubjectRawId' => 'subject-1',
                    'tenantId' => 'tenant-1',
                ],
            ],
            $this->subject->validateAndNormalize($raw),
        );
    }

    public function testValidateAndNormalizeThrowsOnInvalidJson(): void
    {
        $this->expectException(RequestValidationException::class);
        $this->expectExceptionMessageMatches('/^Invalid JSON message body\./');

        $this->subject->validateAndNormalize([
            'body' => '{',
            'headers' => ['type' => 'removal.request'],
        ]);
    }

    public function testValidateAndNormalizeThrowsWhenMissingRequiredRawFields(): void
    {
        $this->expectException(RequestValidationException::class);

        $this->subject->validateAndNormalize([
            'body' => '',
            'headers' => [],
        ]);
    }

    public function testValidateAndNormalizeThrowsWhenMissingHeaderType(): void
    {
        $this->expectException(RequestValidationException::class);

        $this->subject->validateAndNormalize([
            'body' => json_encode([
                'policyId' => 'policy-1',
                'policyVersion' => '1',
                'dataSubjectRawId' => 'subject-1',
                'tenantId' => 'tenant-1',
            ], JSON_THROW_ON_ERROR),
            'headers' => [],
        ]);
    }

    public function testValidateAndNormalizeThrowsWhenBodyMissingRequiredFields(): void
    {
        $this->expectException(RequestValidationException::class);

        $this->subject->validateAndNormalize([
            'body' => json_encode([
                'policyId' => 'policy-1',
                'policyVersion' => '1',
                # dataSubjectRawId is missing
                'tenantId' => 'tenant-1',
            ], JSON_THROW_ON_ERROR),
            'headers' => ['type' => 'removal.request'],
        ]);
    }

    private function createValidator(): ValidatorInterface
    {
        return Validation::createValidator();
    }
}
