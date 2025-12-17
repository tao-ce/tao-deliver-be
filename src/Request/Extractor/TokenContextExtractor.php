<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Request\Extractor;

use App\Action\Security\Lti\LtiLaunchActionCommonTrait;
use App\Lti\LtiCustomSettings;
use App\Request\Domain\Context;
use App\Request\Extractor\Contract\ContextExtractorInterface;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Token\Parser;
use OAT\Library\EnvironmentManagementClient\Exception\EnvironmentManagementClientException;
use OAT\Library\EnvironmentManagementClient\Http\JWTTokenExtractorInterface;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

class TokenContextExtractor implements ContextExtractorInterface
{
    use LtiLaunchActionCommonTrait;

    private DataSet $claims;
    private ?Context $context = null;

    public function __construct(
        private readonly RouterInterface $router,
        private readonly HttpMessageFactoryInterface $httpMessageFactory,
        private readonly JWTTokenExtractorInterface $tokenExtractor,
        private readonly LtiCustomSettings $customSettings,
    ) {
    }

    public function supports(Request $request): bool
    {
        if ($request->query->has('jwt')) {
            try {
                $this->claims = (new Parser(new JoseEncoder()))->parse($request->query->get('jwt'))->claims();
                return true;
            } catch (Throwable) {
            }
        }

        try {
            $this->claims = $this->tokenExtractor->extract(
                $this->httpMessageFactory->createRequest(
                    $request,
                ),
            )->claims();
        } catch (EnvironmentManagementClientException) {
            return false;
        }

        return true;
    }

    public function extract(): Context
    {
        if ($this->context === null) {
            $this->context = new Context();

            if ($this->claims->has('tenant_id')) {
                $this->context = $this->context->withTenantId(
                    $this->claims->get('tenant_id'),
                );
            }

            $ltiClaims = $this->claims->get('ltiClaims', $this->claims->all());

            if (!empty($ltiClaims[LtiMessagePayloadInterface::CLAIM_LTI_TARGET_LINK_URI])) {
                $targetUrl = $ltiClaims[LtiMessagePayloadInterface::CLAIM_LTI_TARGET_LINK_URI];
                $targetLinkUriParts = explode(
                    '/',
                    $targetUrl,
                );
                $id = end($targetLinkUriParts);
                $batteryLaunchPrefix = preg_replace(
                    '/\s?\{[^}]+}/',
                    '',
                    $this->router->getRouteCollection()->get('api_v1_launch_lti_1p3_battery')->getPath(),
                );

                $this->context = str_contains($targetUrl, $batteryLaunchPrefix)
                    ? $this->context->withBatteryId($id)
                    : $this->context->withDeliveryId($id);
            }
            if (!empty($ltiClaims['sub'])) {
                $this->context = $this->context->withUserId(
                    $ltiClaims['sub'],
                );
            }
            if ($this->customSettings->isReviewModeEnabled()) {
                $this->context = $this->context->withReview();
            }
        }

        return $this->context;
    }
}
