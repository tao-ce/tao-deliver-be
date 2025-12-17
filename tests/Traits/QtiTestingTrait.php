<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Traits;

use App\Tests\Helpers\ContainerAwareTestingHelper;

trait QtiTestingTrait
{
    protected function copyCompiledTestToStorage(
        array $files = ['compact-test.xml'],
        string $packageName = 'Basic',
    ): void {
        ContainerAwareTestingHelper::checkKernelTestCase(static::class);

        $qtiCompiledDeliveriesStorage = static::getContainer()->get('qti_compiled_deliveries.storage');

        $rootDir = __DIR__ . '/../Resources/Qti/CompiledPackages/' . $packageName;

        foreach ($files as $file) {
            $qtiCompiledDeliveriesStorage->write(
                $packageName . DIRECTORY_SEPARATOR . $file,
                file_get_contents($rootDir . DIRECTORY_SEPARATOR . $file),
            );
        }
    }
}
