<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Logger\Processor;

use Monolog\LogRecord;

class RequestHeaderAwareProcessor
{
    private array $extraHeaders;

    public function __construct(...$extraHeaders)
    {
        foreach ($extraHeaders as $extraHeader) {
            $this->extraHeaders[$extraHeader] = true;
        }
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $record['extra'] = array_replace(
            array_intersect_key(\getallheaders(), $this->extraHeaders),
            $record['extra'] ?? [],
        );

        return $record;
    }
}
