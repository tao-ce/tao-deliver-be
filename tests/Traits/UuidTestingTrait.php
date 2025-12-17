<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Tests\Traits;

trait UuidTestingTrait
{
    protected function assertUuidV4(string $uuid): void
    {
        $this->assertMatchesRegularExpression($this->getUuidV4Regexp(), $uuid);
    }

    private function getUuidV4Regexp(): string
    {
        return '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i';
    }
}
