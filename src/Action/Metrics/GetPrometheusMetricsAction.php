<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Metrics;

use App\Service\Metrics\PrometheusMetricsCollector;
use Symfony\Component\HttpFoundation\Response;

class GetPrometheusMetricsAction
{
    /** @var PrometheusMetricsCollector */
    private $collector;

    public function __construct(PrometheusMetricsCollector $collector)
    {
        $this->collector = $collector;
    }

    public function __invoke(): Response
    {
        $responseBody = '';
        foreach ($this->collector->collect() as $key => $value) {
            $responseBody .= sprintf('%s %s', $key, $value) . PHP_EOL;
        }

        return new Response($responseBody);
    }
}
