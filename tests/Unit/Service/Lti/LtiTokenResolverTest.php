<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Unit\Service\Lti;

use App\Service\Lti\LtiTokenResolver;
use App\Tests\Traits\DomainTestingTrait;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use OAT\Library\EnvironmentManagementClient\Http\JWTTokenExtractorInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class LtiTokenResolverTest extends KernelTestCase
{
    use DomainTestingTrait;

    private UnencryptedToken $jwtToken;
    private RequestStack $requestStack;
    private LtiTokenResolver $sut;

    /**
     * @before
     */
    public function init(): void
    {
        $jwtConfiguration = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::base64Encoded(base64_encode(random_bytes(32))),
        );
        $this->jwtToken = $jwtConfiguration->builder()->getToken(
            $jwtConfiguration->signer(),
            $jwtConfiguration->signingKey(),
        );
        $request = Request::create('http://localhost');
        $this->requestStack = new RequestStack();
        $this->requestStack->push($request);

        $this->sut = new LtiTokenResolver(
            $this->requestStack,
            $this->getContainer()->get(HttpMessageFactoryInterface::class),
            $this->getContainer()->get(JWTTokenExtractorInterface::class),
            new Parser(new JoseEncoder()),
        );
    }

    public function testResolveFromAuthorizationRequestHeader(): void
    {
        $this->requestStack->getCurrentRequest()->headers->set('authorization', "Bearer {$this->jwtToken->toString()}");

        $this->assertEquals(
            $this->jwtToken,
            $this->sut->resolve(
                $this->createTestDeliveryExecution(),
            ),
        );
    }

    public function testResolveFromRequestQueryParameter(): void
    {
        $this->requestStack->getCurrentRequest()->query->set('id_token', $this->jwtToken->toString());

        $this->assertEquals(
            $this->jwtToken,
            $this->sut->resolve(
                $this->createTestDeliveryExecution(),
            ),
        );
    }

    public function testResolveFromRequestBodyParameter(): void
    {
        $this->requestStack->getCurrentRequest()->request->set('id_token', $this->jwtToken->toString());

        $this->assertEquals(
            $this->jwtToken,
            $this->sut->resolve(
                $this->createTestDeliveryExecution(),
            ),
        );
    }

    public function testResolveFromPersistedToken(): void
    {
        $this->assertEquals(
            $this->jwtToken,
            $this->sut->resolve(
                $this->createTestDeliveryExecution(ltiLaunchParameters: ['id_token' => $this->jwtToken->toString()]),
            ),
        );
    }

    public function testResolveFromPersistedTokenWithNoReuqest(): void
    {
        $this->requestStack->pop();

        $this->assertEquals(
            $this->jwtToken,
            $this->sut->resolve(
                $this->createTestDeliveryExecution(ltiLaunchParameters: ['id_token' => $this->jwtToken->toString()]),
            ),
        );
    }
}
