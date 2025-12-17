<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Validator\DeliveryExecution;

use App\Tests\Traits\RequestTestingTrait;
use App\Validator\DeliveryExecution\ProcessActionsActionRequestValidator;
use App\Validator\Exception\RequestValidationException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProcessActionsActionRequestValidatorTest extends KernelTestCase
{
    use RequestTestingTrait;

    /** @var ProcessActionsActionRequestValidator */
    private $subject;

    public function setUp(): void
    {
        parent::setUp();

        static::bootKernel();

        /** @var ValidatorInterface $validator */
        $validator = static::getContainer()->get(ValidatorInterface::class);

        $this->subject = new ProcessActionsActionRequestValidator($validator);
    }

    public function testValidationSuccess(): void
    {
        $inputData = [
            [
                'channel' => 'channel',
                'message' => [
                    'actions' => [
                        [
                            'id' => 'action_id',
                            'name' => 'action',
                            'timestamp' => null,
                            'parameters' => [],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals(
            $inputData,
            $this->subject->getValidatedRequestParameters($this->createRequest($inputData)),
        );
    }

    /**
     * @dataProvider getValidationTypeFailingCases
     */
    public function testValidationFailureWhenGivenValueHasWrongType(array $actions, string $expectedErrorMessage): void
    {
        try {
            $this->subject->getValidatedRequestParameters($this->createRequest($actions));
            $this->fail('Expected exception was not thrown during validation.');
        } catch (RequestValidationException $exception) {
            $this->assertEquals($expectedErrorMessage, $exception->getMessage());
        }
    }

    public function getValidationTypeFailingCases(): array
    {
        return [
            [
                [
                    [
                        'channel' => 'channel',
                        'message' => [
                            'actions' => [],
                        ],
                    ],
                ],
                'expectedErrorMessage' => '[0][message][actions]: This value should not be blank.',
            ],
            [
                [
                    [
                        'channel' => 'channel',
                        'message' => [
                            'actions' => 'string',
                        ],
                    ],
                ],
                'expectedErrorMessage' => '[0][message][actions]: This value should be of type array., [0][message][actions]: This value should be of type iterable.',
            ],
            [
                [
                    [
                        'channel' => 'channel',
                        'message' => [
                            'actions' => null,
                        ],
                    ],
                ],
                'expectedErrorMessage' => '[0][message][actions]: This value should not be blank.',
            ],
            [
                [
                    [
                        'channel' => 1,
                        'message' => [
                            'actions' => [
                                [
                                    'name' => 'action',
                                    'id' => 'action_12345',
                                    'timestamp' => null,
                                    'parameters' => [],
                                ],
                            ],
                        ],
                    ],
                ],
                'expectedErrorMessage' => '[0][channel]: This value should be of type string.',
            ],
        ];
    }
}
