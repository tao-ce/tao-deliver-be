<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Acs;

use OAT\Library\Lti1p3Core\Security\OAuth2\Validator\Result\RequestAccessTokenValidationResult;
use OAT\Library\Lti1p3Proctoring\Service\Server\Handler\AcsServiceServerRequestHandler;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AcsAction
{
    public function __construct(
        private HttpMessageFactoryInterface $psrHttpFactory,
        private HttpFoundationFactoryInterface $foundationHttpFactory,
        private AcsServiceServerRequestHandler $requestHandler,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        return $this->foundationHttpFactory->createResponse(
            $this->requestHandler->handleValidatedServiceRequest(
                new RequestAccessTokenValidationResult(),
                $this->psrHttpFactory->createRequest($request),
            ),
        );
    }
}
