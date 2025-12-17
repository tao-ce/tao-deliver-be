<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Locale;

use App\Service\Locale\Contract\UserLocaleProviderInterface;
use App\Service\Locale\Dto\UserLocaleProviderContext;
use App\Service\Lti\LtiTokenResolverInterface;
use OAT\Library\Lti1p3Core\Message\Payload\LtiMessagePayloadInterface;

readonly class UserLocaleProvider implements UserLocaleProviderInterface
{
    public function __construct(private LtiTokenResolverInterface $ltiTokenResolver, private string $defaultLocale)
    {
    }

    public function provide(UserLocaleProviderContext $userLocalProviderContext): string
    {
        $ltiParameters = $userLocalProviderContext->deliveryExecution->getLtiLaunchParameters();
        $claims = $this->ltiTokenResolver->resolveFromRequest()?->claims()->all() ?? [];
        $deliveryConfiguration = $userLocalProviderContext->delivery?->getConfiguration() ?? [];

        return $claims[LtiMessagePayloadInterface::CLAIM_LTI_LAUNCH_PRESENTATION]['locale']
            ?? $claims['locale']
            ?? $ltiParameters['launch_presentation_locale']
            ?? $ltiParameters['user_locale']
            ?? $deliveryConfiguration['locale']
            ?? $userLocalProviderContext->testRunnerConfiguration['locale']
            ?? $this->defaultLocale;
    }
}
