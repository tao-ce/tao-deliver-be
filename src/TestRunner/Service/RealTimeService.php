<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA.
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\Service;

class RealTimeService
{
    public function __construct(private readonly ?string $socketConnectionUrl)
    {
    }

    public function getSocketConnectionUrl(): ?string
    {
        return $this->socketConnectionUrl;
    }

    public function isEnabled(): bool
    {
        return (bool)$this->socketConnectionUrl;
    }

    public function getConfiguration(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'socketConnectionUrl' => $this->getSocketConnectionUrl(),
        ];
    }
}
