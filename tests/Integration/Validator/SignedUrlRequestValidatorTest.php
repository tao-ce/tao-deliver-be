<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Validator;

use App\Tests\Traits\RequestTestingTrait;
use App\Validator\Exception\RequestValidationException;
use App\Validator\SignedUrlRequestValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SignedUrlRequestValidatorTest extends KernelTestCase
{
    use RequestTestingTrait;

    /** @var SignedUrlRequestValidator */
    private $subject;

    public function setUp(): void
    {
        parent::setUp();

        static::bootKernel();

        $this->subject = new SignedUrlRequestValidator(static::getContainer()->get(ValidatorInterface::class));
    }

    public function testValidationSuccess(): void
    {
        $expected = [
            'path' => 'somePath',
        ];

        $request = $this->createRequest(
            [],
            '/uri',
            Request::METHOD_GET,
            [
                'path' => 'somePath',
            ],
        );

        $this->assertEquals($expected, $this->subject->getValidatedRequestParameters($request));
    }
}
