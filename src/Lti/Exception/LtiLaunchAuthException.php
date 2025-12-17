<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Lti\Exception;

use Symfony\Component\HttpFoundation\Response;

class LtiLaunchAuthException extends LtiLaunchException
{
    public function getStatusCode(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }
}
