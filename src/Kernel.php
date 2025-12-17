<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App;

use App\DependencyInjection\CompilerPass\DefaultExtraHttpClientOptionsPass;
use App\DependencyInjection\CompilerPass\GpsConnectionPass;
use App\DependencyInjection\CompilerPass\LoggerTaggerPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        $container
            ->addCompilerPass(new LoggerTaggerPass())
            ->addCompilerPass(new DefaultExtraHttpClientOptionsPass())
            ->addCompilerPass(new GpsConnectionPass());
    }
}
