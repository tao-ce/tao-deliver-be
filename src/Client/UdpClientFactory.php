<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Client;

class UdpClientFactory
{
    public function create(string $ip, int $port): UdpClient
    {
        return new UdpClient($ip, $port);
    }
}
