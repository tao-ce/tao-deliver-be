<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Logger\Processor;

use Monolog\LogRecord;
use App\Command\Worker\WorkerContext\CurrentWorkerContext;
use App\Command\Worker\WorkerContext\CurrentWorkerContextProvider;

class CurrentWorkerContextAwareProcessor
{
    public function __construct(private CurrentWorkerContextProvider $currentWorkerContextProvider)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $currentContext = $this->currentWorkerContextProvider->provide();

        if ($currentContext instanceof CurrentWorkerContext) {
            $record['extra'] = array_replace(
                ['worker' => $currentContext->getWorkerName()],
                $record['extra'] ?? [],
            );
        }

        return $record;
    }
}
