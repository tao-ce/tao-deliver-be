<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Tests\Traits;

use Symfony\Component\HttpFoundation\Request;

trait RequestTestingTrait
{
    protected function createRequest(
        array $rawContent = [],
        string $uri = '/uri',
        string $method = Request::METHOD_POST,
        array $parameters = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
    ): Request {
        return Request::create($uri, $method, $parameters, $cookies, $files, $server, json_encode($rawContent));
    }
}
