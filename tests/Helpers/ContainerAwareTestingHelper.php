<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Tests\Helpers;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ContainerAwareTestingHelper
{
    public static function checkKernelTestCase($class): void
    {
        if (!is_a($class, KernelTestCase::class, true)) {
            throw new LogicException(
                sprintf('The test class must extend "%s" to use "%s".', KernelTestCase::class, $class),
            );
        }
    }

    public static function checkWebTestCase($class): void
    {
        if (!is_a($class, WebTestCase::class, true)) {
            throw new LogicException(
                sprintf('The test class must extend "%s" to use "%s".', WebTestCase::class, $class),
            );
        }
    }
}
