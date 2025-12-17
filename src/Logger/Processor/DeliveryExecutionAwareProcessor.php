<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Logger\Processor;

use App\Request\Service\ContextService;
use Monolog\LogRecord;

class DeliveryExecutionAwareProcessor
{
    public function __construct(private ContextService $contextService)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $record['extra'] = array_replace(
            $this->contextService->fetch()->toArray(),
            $record['extra'] ?? [],
        );

        return $record;
    }
}
