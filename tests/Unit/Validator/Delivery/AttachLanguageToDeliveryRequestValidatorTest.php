<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Tests\Unit\Validator\Delivery;

use App\Validator\Delivery\AttachLanguageToDeliveryRequestValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AttachLanguageToDeliveryRequestValidatorTest extends TestCase
{
    private ValidatorInterface $validator;
    private AttachLanguageToDeliveryRequestValidator $requestValidator;

    protected function setUp(): void
    {
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->requestValidator = new AttachLanguageToDeliveryRequestValidator($this->validator);
    }

    public function testGetValidatedRequestParametersValidRequest()
    {
        $requestData = ['package' => 'base64PackageContent'];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $validatedData = $this->requestValidator->getValidatedRequestParameters($request);

        $this->assertEquals($requestData, $validatedData);
    }

    public function testGetValidatedRequestParametersThrowsExceptionIfPackageOrPackageRefMissing()
    {
        $requestData = [];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $this->expectException(ValidationFailedException::class);
        $this->expectExceptionMessage('Either "package" or "packageRef" must be provided.');

        $this->requestValidator->getValidatedRequestParameters($request);
    }
}
