<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Validator\Log;

use App\Registry\LoggerRegistry;
use App\Tests\Traits\RequestTestingTrait;
use App\Validator\Log\CreateLogsActionRequestValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreateLogActionRequestValidatorTest extends KernelTestCase
{
    use RequestTestingTrait;

    /** @var CreateLogsActionRequestValidator */
    private $subject;

    public function setUp(): void
    {
        parent::setUp();

        static::bootKernel();

        $this->subject = new CreateLogsActionRequestValidator(
            static::getContainer()->get(ValidatorInterface::class),
            static::getContainer()->get(LoggerRegistry::class),
        );
    }

    /**
     * @dataProvider validationDataProvider
     */
    public function testValidation(
        ?string $level = null,
        ?string $message = null,
        ?string $type = null,
        $context = null,
        ?string $expectedExceptionMessage = null,
    ): void {
        $request = new Request([], [], [], [], [], [], json_encode([
            [
                'type' => $type,
                'message' => $message,
                'level' => $level,
                'context' => $context,
            ],
        ]));

        if ($expectedExceptionMessage !== null) {
            $this->expectExceptionMessage($expectedExceptionMessage);
        }

        $result = $this->subject->getValidatedRequestParameters($request);

        if ($expectedExceptionMessage === null) {
            $this->assertEquals($type, $result[0]['type']);
            $this->assertEquals($message, $result[0]['message']);
            $this->assertEquals($level, $result[0]['level']);
        }
    }

    public function validationDataProvider(): array
    {
        return [
            ['debug', 'message', 'default', [], null],
            ['info', 'message', 'default', [], null],
            ['notice', 'message', 'default', [], null],
            ['warning', 'message', 'default', [], null],
            ['error', 'message', 'default', [], null],
            ['critical', 'message', 'default', [], null],
            ['alert', 'message', 'default', [], null],
            ['emergency', 'message', 'default', [], null],
            ['debug', '', 'default', [], '[0][message]: This value should not be blank.'],
            ['debug', 'message', '', [], '[0][type]: The value you selected is not a valid choice.'],
            ['debug', 'message', null, [], null],
            [null, null, null, [], '[0][message]: This value should not be blank., [0][level]: This value should not be blank.'],
            ['invalid level', 'message', 'type', [], '[0][level]: The value you selected is not a valid choice.'],
            ['debug', 'message', 'default', 'invalid context', '[0][context]: This value should be of type array.'],
            ['debug', 'message', 'default', null, null],
        ];
    }
}
