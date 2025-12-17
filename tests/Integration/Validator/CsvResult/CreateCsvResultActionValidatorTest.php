<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Validator\CsvResult;

use App\Tests\Traits\RequestTestingTrait;
use App\Validator\CsvResult\CreateCsvResultActionValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreateCsvResultActionValidatorTest extends KernelTestCase
{
    use RequestTestingTrait;

    /** @var CreateCsvResultActionValidator */
    private $subject;

    protected function setUp(): void
    {
        static::bootKernel();

        $this->subject = new CreateCsvResultActionValidator(static::getContainer()->get(ValidatorInterface::class));
    }

    public function testValidationForEmptyContent(): void
    {
        $request = new Request();

        $parameters = $this->subject->getValidatedRequestParameters($request);

        $this->assertNull($parameters['limit']);
    }

    public function testLimitValidation(): void
    {
        $request = new Request([
            'limit' => '23',
        ]);

        $parameters = $this->subject->getValidatedRequestParameters($request);

        $this->assertSame('23', $parameters['limit']);
    }

    public function testLimitValidationMin(): void
    {
        $request = new Request([
            'limit' => '0',
        ]);

        $this->expectExceptionMessage('[limit]: This value should be greater than 0.');
        $this->subject->getValidatedRequestParameters($request);
    }

    public function testLimitValidationMax(): void
    {
        $request = new Request([
            'limit' => PHP_INT_MAX,
        ]);


        $this->expectExceptionMessage(sprintf('[limit]: This value should be less than %d.', PHP_INT_MAX));
        $this->subject->getValidatedRequestParameters($request);
    }
}
