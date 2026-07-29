<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Validator\Messenger\DataPolicy;

use App\Validator\Exception\RequestValidationException;
use App\Validator\Messenger\DataPolicy\ValidationRequestMessageValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationRequestMessageValidatorTest extends TestCase
{
    private ValidationRequestMessageValidator $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new ValidationRequestMessageValidator($this->createValidator());
    }

    public function testValidateAndNormalizeReturnsRawWithDecodedBodyAndType(): void
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

        self::assertSame(
            [
                'type' => 'validation.request',
                'body' => [
                    'tenantId' => 'tenant-1',
                    'dataSubjectRawId' => 'subject-1',
                    'policyId' => 'policy-1',
                    'policyVersion' => '1',
                    'ownerApp' => 'deliver',
                ],
            ],
            $this->subject->validateAndNormalize($raw),
        );
    }

    public function testValidateAndNormalizeThrowsOnMissingOwnerApp(): void
    {
        $this->expectException(RequestValidationException::class);

        $raw = [
            'body' => json_encode([
                'tenantId' => 'tenant-1',
                'dataSubjectRawId' => 'subject-1',
                'policyId' => 'policy-1',
                'policyVersion' => '1',
            ], JSON_THROW_ON_ERROR),
            'headers' => [
                'type' => 'validation.request',
            ],
        ];

        $this->subject->validateAndNormalize($raw);
    }

    private function createValidator(): ValidatorInterface
    {
        return Validation::createValidator();
    }
}
