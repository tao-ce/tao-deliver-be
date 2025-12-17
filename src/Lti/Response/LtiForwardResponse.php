<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

class LtiForwardResponse extends JsonResponse
{
    public function __construct(string $url, int $status = 200, array $headers = [])
    {
        parent::__construct(
            ['redirectionURL' => $url],
            $status,
            $headers,
        );
    }
}
