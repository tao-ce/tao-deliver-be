<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Command\Worker\WorkerContext;

/**
 * @deprecated Should be removed with worker commands
 */
class CurrentWorkerContext
{
    public function __construct(private string $workerName)
    {
    }

    public function getWorkerName(): string
    {
        return $this->workerName;
    }
}
