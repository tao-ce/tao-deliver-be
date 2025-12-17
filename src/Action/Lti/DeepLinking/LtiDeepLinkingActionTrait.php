<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Lti\DeepLinking;

use OAT\Library\Lti1p3Core\Exception\LtiExceptionInterface;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\DeepLinkingSettingsClaim;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3DeepLinking\Message\Launch\Builder\DeepLinkingLaunchResponseBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

trait LtiDeepLinkingActionTrait
{
    public function __construct(
        private readonly DeepLinkingLaunchResponseBuilder $deepLinkingLaunchResponseBuilder,
    ) {
    }

    /**
     * @throws LtiExceptionInterface
     */
    private function getDeepLinkingErrorResponse(
        RegistrationInterface $registration,
        DeepLinkingSettingsClaim $deepLinkingSettingsClaim,
        Throwable $exception,
        ?string $deploymentId = null,
        bool $redirect = false,
    ): Response {
        $url = $this->deepLinkingLaunchResponseBuilder
            ->buildDeepLinkingLaunchErrorResponse(
                $registration,
                $deepLinkingSettingsClaim->getDeepLinkingReturnUrl(),
                $deploymentId,
                $deepLinkingSettingsClaim->getData(),
                $exception->getMessage(),
                $exception->getMessage(),
            )
            ->toUrl();

        if ($redirect) {
            return new RedirectResponse($url);
        }

        return new JsonResponse(['url' => $url]);
    }
}
