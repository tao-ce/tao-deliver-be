<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Traits;

trait JwtTestingTrait
{
    private function getJwtNormalizedPayload(string $jwt): array
    {
        return json_decode(
            base64_decode(explode('.', $jwt)[1]),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
