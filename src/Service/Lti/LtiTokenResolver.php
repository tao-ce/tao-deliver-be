<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Service\Lti;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use OAT\Library\EnvironmentManagementClient\Exception\EnvironmentManagementClientException;
use OAT\Library\EnvironmentManagementClient\Http\JWTTokenExtractorInterface;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class LtiTokenResolver implements LtiTokenResolverInterface
{
    private const LTI_CLAIM_ROLE_BASIS = 'http://purl.imsglobal.org/vocab/lis/v2/membership/';
    private false|null|UnencryptedToken $token = false;

    public function __construct(
        private readonly RequestStack $requestStack,
        private HttpMessageFactoryInterface $httpMessageFactory,
        private JWTTokenExtractorInterface $jwtTokenExtractor,
        private Parser $jwtParser,
    ) {
    }

    public function hasOneOfRoles(array $roles): bool
    {
        $token = $this->resolveFromRequest();
        if (null === $token) {
            return false;
        }

        $claimRoles = $token->claims()?->all()[LtiMessagePayloadInterface::CLAIM_LTI_ROLES] ?? null;
        if (null === $claimRoles) {
            return false;
        }

        // filter roles to left only basis
        if (in_array(self::LTI_CLAIM_ROLE_BASIS, $roles)) {
            $roles = array_map(
                fn(string $role) => explode('#', $role)[0],
                $roles,
            );

            $claimRoles = array_map(
                fn(string $role) => explode('#', $role)[0],
                $claimRoles,
            );
        }

        return !empty(
            array_intersect($roles, $claimRoles)
        );
    }

    public function resolve(DeliveryExecution $deliveryExecution): UnencryptedToken
    {
        $token = $this->resolveFromRequest();
        if (null !== $token) {
            return $token;
        }

        return $this->jwtParser->parse(
            $deliveryExecution->getLtiToken(),
        );
    }

    public function resolveFromRequest(): ?UnencryptedToken
    {
        if (false !== $this->token) {
            return $this->token;
        }

        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            $this->token = null;
            return $this->token;
        }

        try {
            $this->token = $this->jwtTokenExtractor->extract(
                $this->httpMessageFactory->createRequest($request),
            );
            return $this->token;
        } catch (EnvironmentManagementClientException) {
        }

        $rawToken = $request->get('id_token');
        if (null === $rawToken) {
            $this->token = null;
            return $this->token;
        }

        $this->token = $this->jwtParser->parse($rawToken);
        return $this->token;
    }
}
