<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Validator\Delivery;

use App\Tests\Traits\RequestTestingTrait;
use App\Validator\Delivery\UpdateDeliveryRequestValidator;
use App\Validator\Exception\RequestValidationException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

class UpdateDeliveryRequestValidatorTest extends KernelTestCase
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

    /** @var UpdateDeliveryRequestValidator */
    private $subject;

    public function setUp(): void
    {
        parent::setUp();

        static::bootKernel();

        $this->subject = new UpdateDeliveryRequestValidator(static::getContainer()->get(ValidatorInterface::class));
    }

    public function testValidationSuccess(): void
    {
        $this->assertEquals(
            self::VALID_CONFIGURATION,
            $this->subject->getValidatedRequestParameter(
                $this->createRequest(
                    [
                        'configuration' => self::VALID_CONFIGURATION,
                    ],
                    '/uri',
                    Request::METHOD_PATCH,
                ),
                'configuration',
            ),
        );
    }

    /**
     * @dataProvider invalidConfigurationsProvider
     */
    public function testValidationFailureWhenConfigurationIsNotValid(array $content, string $message): void
    {
        try {
            $this->subject->getValidatedRequestParameters(
                $this->createRequest($content, '/uri', Request::METHOD_PATCH),
            );
            $this->fail('Expected exception was not thrown during validation.');
        } catch (Throwable $exception) {
            $this->assertInstanceOf(RequestValidationException::class, $exception);
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    public function invalidConfigurationsProvider(): array
    {
        return [
            [[], '[configuration][label]: This value should not be blank.'],
            [['configuration' => []], '[configuration][label]: This value should not be blank.'],
            [['configuration' => 'string'], '[configuration][label]: This value should not be blank.'],
            [['configuration' => ['label' => null,'status' => null]], '[configuration][label]: This value should not be blank.'],
            [['configuration' => ['label' => 'label','status' => 'string']], '[configuration][status]: This value should be of type bool.'],
            [['configuration' => ['label' => 'label','metadata' => 'string']], '[configuration][metadata]: This value should be of type iterable.'],
            [['configuration' => ['label' => 'label','metadata' => []]], '[configuration][metadata]: This value should not be blank.'],
            [['configuration' => ['label' => 'label','metadata' => ['property_1' => []]]], '[configuration][metadata][property_1]: This value should not be blank.'],
            [['configuration' => ['label' => 'label','metadata' => ['property_1' => 'value']]], '[configuration][metadata][property_1]: This value should be of type iterable.'],
            [['configuration' => true], '[configuration][label]: This value should not be blank.'],
        ];
    }
}
