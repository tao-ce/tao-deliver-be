<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Validator\Publication;

use App\Repository\DeliveryRepository;
use App\Tests\Traits\RequestTestingTrait;
use App\Validator\Exception\RequestValidationException;
use App\Validator\Locale\LocaleValidator;
use App\Validator\Publication\CreatePublicationRequestValidator;
use InvalidArgumentException;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreatePublicationRequestValidatorTest extends KernelTestCase
{
    use RequestTestingTrait;

    private const VALID_CONFIGURATION = [
        'label' => 'test',
        'status' => true,
        'availabilityDate' => 12345,
        'expiryDate' => 12345,
        'metadata' => [
            'property_1' => ['value_1'],
            'property_2' => ['value_2', 'value_3'],
            'property_3' => [null],
        ],
    ];

    private readonly DeliveryRepository $deliveryRepositoryMock;
    private readonly LocaleValidator $localeValidatorMock;
    private readonly CreatePublicationRequestValidator $subject;

    public function setUp(): void
    {
        parent::setUp();
        static::bootKernel();

        $this->deliveryRepositoryMock = $this->createMock(DeliveryRepository::class);
        $this->localeValidatorMock = $this->createMock(LocaleValidator::class);

        $this->subject = new CreatePublicationRequestValidator(
            $this->deliveryRepositoryMock,
            $this->localeValidatorMock,
            static::getContainer()->get(ValidatorInterface::class),
        );
    }

    public function testValidationSuccess(): void
    {
        $dataToValidate = [
            'package' => 'package',
            'packageRef' => 'http://package.test/location',
            'deliveryId' => null,
            'configuration' => static::VALID_CONFIGURATION,
            'locale' => 'en',
            'translations' => [],
        ];

        $this->assertEquals(
            $dataToValidate,
            $this->subject->getValidatedRequestParameters($this->createRequest(array_filter($dataToValidate))),
        );
    }

    public function testValidationSuccessWithDeliveryId(): void
    {
        $dataToValidate = [
            'package' => 'package',
            'packageRef' => 'http://package.test/location',
            'deliveryId' => 'delivery_id',
            'configuration' => static::VALID_CONFIGURATION,
            'locale' => 'en',
            'translations' => [],
        ];

        $this->deliveryRepositoryMock
            ->method('find')
            ->with('delivery_id')
            ->willThrowException(new DocumentNotFoundException());

        $this->assertEquals(
            $dataToValidate,
            $this->subject->getValidatedRequestParameters($this->createRequest($dataToValidate)),
        );
    }

    public function testValidationFailsWithDuplicateDeliveryId(): void
    {
        $dataToValidate = [
            'package' => 'package',
            'packageRef' => 'http://package.test/location',
            'deliveryId' => 'delivery_id',
            'configuration' => static::VALID_CONFIGURATION,
        ];

        $this->expectExceptionObject(
            new RequestValidationException('[deliveryId]: #delivery_id delivery already exists.'),
        );

        $this->subject->getValidatedRequestParameters($this->createRequest($dataToValidate));
    }

    public function testValidationSuccessWithLocale(): void
    {
        $dataToValidate = [
            'package' => 'package',
            'packageRef' => 'http://package.test/location',
            'deliveryId' => 'delivery_id',
            'configuration' => static::VALID_CONFIGURATION,
            'locale' => 'en',
            'translations' => [],
        ];

        $this->deliveryRepositoryMock
            ->method('find')
            ->with('delivery_id')
            ->willThrowException(new DocumentNotFoundException());


        $this->assertEquals(
            $dataToValidate,
            $this->subject->getValidatedRequestParameters($this->createRequest($dataToValidate)),
        );
    }

    public function testValidationFailsWithInvalidLocale(): void
    {
        $dataToValidate = [
            'package' => 'package',
            'packageRef' => 'http://package.test/location',
            'deliveryId' => 'delivery_id',
            'configuration' => static::VALID_CONFIGURATION,
            'locale' => 'eng',
        ];

        $this->localeValidatorMock
            ->method('validate')
            ->with('eng')
            ->willThrowException(new InvalidArgumentException('Locale [eng] has invalid format'));

        $this->deliveryRepositoryMock
            ->method('find')
            ->with('delivery_id')
            ->willThrowException(new DocumentNotFoundException());

        $this->expectExceptionObject(
            new RequestValidationException('[locale]: Locale [eng] has invalid format'),
        );

        $this->subject->getValidatedRequestParameters($this->createRequest($dataToValidate));
    }

    /**
     * @dataProvider getMandatoryParameterFailingCases
     */
    public function testValidationFailureWhenMandatoryParametersIsMissing($data): void
    {
        $expectedErrorMessage = $data['expectedErrorMessage'];

        unset($data['expectedErrorMessage']);

        try {
            $this->subject->getValidatedRequestParameters($this->createRequest($data));
            $this->fail('Expected exception was not thrown during validation.');
        } catch (RequestValidationException $exception) {
            $this->assertStringContainsString($expectedErrorMessage, $exception->getMessage());
        }
    }

    /**
     * @dataProvider getValidationTypeFailingCases
     */
    public function testValidationFailureWhenGivenValueHasWrongType($data): void
    {
        $expectedErrorMessage = $data['expectedErrorMessage'];

        unset($data['expectedErrorMessage']);

        try {
            $this->subject->getValidatedRequestParameters($this->createRequest($data));
            $this->fail('Expected exception was not thrown during validation.');
        } catch (RequestValidationException $exception) {
            $this->assertStringContainsString($expectedErrorMessage, $exception->getMessage());
        }
    }

    public function getMandatoryParameterFailingCases(): array
    {
        return [
            [
                [
                    'package' => 'package',
                    'expectedErrorMessage' => '[configuration][label]: This value should not be blank.',
                ],
            ],
            [
                [
                    'package' => 'package',
                    'configuration' => ['configuration'],
                    'expectedErrorMessage' => '[configuration][label]: This value should not be blank.',
                ],
            ],
            [
                [
                    'configuration' => ['configuration'],
                    'expectedErrorMessage' => "[package]: Either 'package' should be provided as base64 encoded string, or 'packageRef' should provide the package location in private bucket.",
                ],
            ],
            [
                [
                    'package' => '',
                    'packageRef' => '',
                    'configuration' => ['configuration'],
                    'expectedErrorMessage' => "[package]: Either 'package' should be provided as base64 encoded string, or 'packageRef' should provide the package location in private bucket.",
                ],
            ],
        ];
    }

    public function getValidationTypeFailingCases(): array
    {
        return [
            [
                [
                    'package' => ['array'],
                    'configuration' => ['configuration'],
                    'expectedErrorMessage' => '[package]: This value should be of type string.',
                ],
            ],
            [
                [
                    'package' => 'package',
                    'configuration' => [],
                    'expectedErrorMessage' => '[configuration][label]: This value should not be blank.',
                ],
            ],
            [
                [
                    'package' => 'package',
                    'configuration' => null,
                    'expectedErrorMessage' => '[configuration][label]: This value should not be blank.',
                ],
            ],
            [
                [
                    'package' => 'package',
                    'configuration' => 'string',
                    'expectedErrorMessage' => '[configuration][label]: This value should not be blank.',
                ],
            ],
            [
                [
                    'package' => 'package',
                    'configuration' => ['label' => 'label', 'status' => 1234],
                    'expectedErrorMessage' => '[configuration][status]: This value should be of type bool.',
                ],
            ],
            [
                [
                    'package' => 'package',
                    'configuration' => ['label' => 'label', 'metadata' => 'string'],
                    'expectedErrorMessage' => '[configuration][metadata]: This value should be of type iterable.',
                ],
            ],
            [
                [
                    'package' => 'package',
                    'configuration' => ['label' => 'label', 'metadata' => []],
                    'expectedErrorMessage' => '[configuration][metadata]: This value should not be blank.',
                ],
            ],
            [
                [
                    'package' => 'package',
                    'configuration' => ['label' => 'label', 'metadata' => ['property_1' => []]],
                    'expectedErrorMessage' => '[configuration][metadata][property_1]: This value should not be blank.',
                ],
            ],
            [
                [
                    'package' => 'package',
                    'configuration' => ['label' => 'label', 'metadata' => ['property_1' => 'value']],
                    'expectedErrorMessage' => '[configuration][metadata][property_1]: This value should be of type iterable.',
                ],
            ],
        ];
    }
}
