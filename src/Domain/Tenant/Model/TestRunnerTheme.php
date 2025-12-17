<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use OAT\Library\TenantManagement\Model\TestRunnerThemeInterface;

class TestRunnerTheme implements TestRunnerThemeInterface
{
    public function __construct(private array $platform, private array $testRunner, private array $itemRunner, private string $default)
    {
    }

    public function getPlatform(): array
    {
        return $this->platform;
    }

    public function getTestRunner(): array
    {
        return $this->testRunner;
    }

    public function getItemRunner(): array
    {
        return $this->itemRunner;
    }

    public function getDefault(): string
    {
        return $this->default;
    }
}
