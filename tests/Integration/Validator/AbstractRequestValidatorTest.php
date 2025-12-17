<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Integration\Validator;

use App\Validator\AbstractRequestValidator;
use App\Validator\Exception\RequestValidationException;
use App\Validator\Security\Jwt\JwtRefreshTokenRequestValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AbstractRequestValidatorTest extends KernelTestCase
{
    /** @var JwtRefreshTokenRequestValidator */
    private $subject;

    public function setUp(): void
    {
        parent::setUp();

        static::bootKernel();

        $this->subject = new class (static::getContainer()->get(ValidatorInterface::class)) extends AbstractRequestValidator {
            protected function getRequestData(Request $request): array
            {
                return [
                    'requestParameter' => $request->get('requestParameter'),
                    'cookieParameter' => $request->cookies->get('cookieParameter'),
                    'serverParameter' => $request->server->get('serverParameter'),
                ];
            }

            protected function getRequestValidationConstraint(): Constraint
            {
                return new Collection([
                    'requestParameter' => [
                        new NotBlank(),
                        new Type(['type' => 'string']),
                    ],
                    'cookieParameter' => [
                        new NotBlank(),
                        new Type(['type' => 'integer']),
                    ],
                    'serverParameter' => [
                        new Type(['type' => 'boolean']),
                    ],
                ]);
            }
        };
    }

    public function testValidationSuccess(): void
    {
        $request = $this->createRequest('string', 10, true);

        $this->assertEquals(
            [
                'requestParameter' => 'string',
                'cookieParameter' => 10,
                'serverParameter' => true,
            ],
            $this->subject->getValidatedRequestParameters($request),
        );

        $this->assertSame('string', $this->subject->getValidatedRequestParameter($request, 'requestParameter'));
        $this->assertSame(10, $this->subject->getValidatedRequestParameter($request, 'cookieParameter'));
        $this->assertTrue($this->subject->getValidatedRequestParameter($request, 'serverParameter'));
    }

    public function testValidationWithDefaultValues(): void
    {
        $request = $this->createRequest('string', 10);

        $this->assertEquals(
            'defaultValue',
            $this->subject->getValidatedRequestParameter($request, 'serverParameter', 'defaultValue'),
        );
    }

    public function testValidationFailure(): void
    {
        $this->expectException(RequestValidationException::class);
        $this->expectExceptionMessage(
            '[requestParameter]: This value should not be blank., [cookieParameter]: This value should not be blank.',
        );

        $this->subject->getValidatedRequestParameters($this->createRequest());
    }

    private function createRequest(mixed $requestParameter = null, mixed $cookieParameter = null, mixed $serverParameter = null): Request
    {
        return Request::create(
            '/uri',
            Request::METHOD_POST,
            ['requestParameter' => $requestParameter],
            ['cookieParameter' => $cookieParameter],
            [],
            ['serverParameter' => $serverParameter],
        );
    }
}
