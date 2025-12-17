<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class LoggerTaggerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $handlersToChannels = $container->getParameter('monolog.handlers_to_channels');

        foreach ($handlersToChannels as $options) {
            if ($options['type'] ?? null === 'inclusive') {
                foreach ($options['elements'] as $channel) {
                    $loggerDefinition = sprintf('monolog.logger.%s', $channel);

                    if ($container->hasDefinition($loggerDefinition)) {
                        $container->getDefinition($loggerDefinition)->addTag('monolog.custom_channel');
                    }
                }
            }
        }
    }
}
