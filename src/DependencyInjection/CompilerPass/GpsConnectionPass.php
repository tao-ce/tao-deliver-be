<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DependencyInjection\CompilerPass;

use App\Factory\GpsConnectionFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class GpsConnectionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $httpHandler = new Reference('http_handler');

        foreach ($container->getDefinitions() as $definition) {
            if ($definition->getClass() !== GpsConnectionFactory::class) {
                continue;
            }

            $definition->setArguments([$httpHandler, ...$definition->getArguments()]);
        }
    }
}
