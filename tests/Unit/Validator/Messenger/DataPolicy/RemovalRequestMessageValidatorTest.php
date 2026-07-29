<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Validator\Messenger\DataPolicy;

use App\Validator\Exception\RequestValidationException;
use App\Validator\Messenger\DataPolicy\RemovalRequestMessageValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RemovalRequestMessageValidatorTest extends TestCase
{
    private RemovalRequestMessageValidator $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new RemovalRequestMessageValidator($this->createValidator());
    }

    public function testValidateAndNormalizeReturnsExpectedStructure(): void
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
                'name' => 'test-name',
                'metadata' => [
                    'deliveryExecutions' => ['de-1', 'de-2'],
                ],
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
                    'ownerApp' => 'deliver',
                    'dataSubjectRawId' => 'subject-1',
                    'tenantId' => 'tenant-1',
                    'uniqueId' => 'unique-1',
                    'storageType' => 'bigtable',
                    'name' => 'test-name',
                    'metadata' => [
                        'deliveryExecutions' => ['de-1', 'de-2'],
                    ],
                ],
                'metadata' => [
                    'deliveryExecutions' => ['de-1', 'de-2'],
                ],
            ],
            $this->subject->validateAndNormalize($raw),
        );
    }

    public function testValidateAndNormalizeThrowsWhenMetadataInvalid(): void
    {
        $this->expectException(RequestValidationException::class);

        $raw = [
            'body' => json_encode([
                'policyId' => 'policy-1',
                'policyVersion' => '1',
                'ownerApp' => 'deliver',
                'dataSubjectRawId' => 'subject-1',
                'tenantId' => 'tenant-1',
                'uniqueId' => 'unique-1',
                'storageType' => 'bigtable',
                'name' => 'test-name',
                'metadata' => [
                    'deliveryExecutions' => [''],
                ],
            ], JSON_THROW_ON_ERROR),
            'headers' => [
                'type' => 'removal.request',
            ],
        ];

        $this->subject->validateAndNormalize($raw);
    }

    public function testValidateAndNormalizeThrowsWhenBodyMissingRequiredFields(): void
    {
        $this->expectException(RequestValidationException::class);

        $raw = [
            'body' => json_encode([
                'policyId' => 'policy-1',
                'policyVersion' => '1',
                # ownerApp is missing
                'dataSubjectRawId' => 'subject-1',
                'tenantId' => 'tenant-1',
                'uniqueId' => 'unique-1',
                'storageType' => 'bigtable',
                'name' => 'test-name',
                'metadata' => [
                    'deliveryExecutions' => ['de-1'],
                ],
            ], JSON_THROW_ON_ERROR),
            'headers' => [
                'type' => 'removal.request',
            ],
        ];

        $this->subject->validateAndNormalize($raw);
    }


    private function createValidator(): ValidatorInterface
    {
        return Validation::createValidator();
    }
}
