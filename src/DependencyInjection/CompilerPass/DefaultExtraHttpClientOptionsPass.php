<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DefaultExtraHttpClientOptionsPass implements CompilerPassInterface
{
    private const ALLOWED_OPTIONS = [
        'extra',
    ];

    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('http_client') || !$container->getParameterBag()->has('http_client')) {
            return;
        }

        $httpClientDefinition = $container->getDefinition('http_client');

        $defaultOptions = array_filter(
            (array)$container->getParameterBag()->get('http_client'),
            static function ($key): bool {
                return in_array($key, self::ALLOWED_OPTIONS, true);
            },
            ARRAY_FILTER_USE_KEY,
        );

        $httpClientDefinition->setArgument(
            0,
            array_replace(
                $httpClientDefinition->getArgument(0),
                $defaultOptions,
            ),
        );
    }
}
