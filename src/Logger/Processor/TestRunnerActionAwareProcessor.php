<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Logger\Processor;

use App\TestRunner\Service\ActionIdProvider;
use Monolog\LogRecord;

class TestRunnerActionAwareProcessor
{
    public function __construct(private readonly ActionIdProvider $actionIdProvider)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $actionId = $this->actionIdProvider->get();
        if (null !== $actionId) {
            $record['extra']['actionId'] = $actionId;
        }

        return $record;
    }
}
