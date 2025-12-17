<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use OAT\Library\TenantManagement\Model\TestRunnerThemeInterface;

class EmptyTestRunnerTheme implements TestRunnerThemeInterface
{
    public function getPlatform(): array
    {
        return [];
    }

    public function getTestRunner(): array
    {
        return [];
    }

    public function getItemRunner(): array
    {
        return [];
    }

    public function getDefault(): string
    {
        return '';
    }
}
