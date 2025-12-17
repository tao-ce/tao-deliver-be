<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Metrics;

use Exception;

class PrometheusMetricsCollector
{
    /**
     * @throws Exception
     */
    public function collect(): array
    {
        $apcuCacheInfo = apcu_cache_info();
        if (false === $apcuCacheInfo) {
            throw new Exception('APCu stats collection failed');
        }

        $apcuSmaInfo = apcu_sma_info();
        if (false === $apcuSmaInfo) {
            throw new Exception('APCu stats collection failed');
        }

        $minAtime = PHP_INT_MAX;
        foreach ($apcuCacheInfo['cache_list'] ?? [] as $obj) {
            $minAtime = min($minAtime, $obj['access_time'] ?? PHP_INT_MAX);
        }

        if ($minAtime == PHP_INT_MAX) {
            $minAtime = -1;
        }

        $metrics = [
            'apcu_num_entries' => $apcuCacheInfo['num_entries'] ?? '-1',
            'apcu_num_hits' => $apcuCacheInfo['num_hits'] ?? '-1',
            'apcu_num_inserts' => $apcuCacheInfo['num_inserts'] ?? '-1',
            'apcu_num_misses' => $apcuCacheInfo['num_misses'] ?? '-1',
            'apcu_num_slots' => $apcuCacheInfo['num_slots'] ?? '-1',
            'apcu_avail_mem' => $apcuSmaInfo['avail_mem'] ?? '-1',
            'apcu_seg_size' => $apcuSmaInfo['seg_size'] ?? '-1',
            'apcu_num_seg' => $apcuSmaInfo['num_seg'] ?? '-1',
            'apcu_min_atime' => $minAtime,
        ];

        $fpmStatus = fpm_get_status();
        foreach ($fpmStatus as $key => $value) {
            if (is_numeric($value)) {
                $metrics[str_replace('-', '_', $key)] = $value;
            }
        }

        foreach ($fpmStatus['procs'] as $proc) {
            $pid = $proc['pid'];
            unset($proc['pid']);
            foreach ($proc as $pkey => $pvalue) {
                if (is_numeric($pvalue)) {
                    $metrics[str_replace('-', '_', "proc_{$pid}_$pkey")] = $pvalue;
                }
            }
        }

        return $metrics;
    }
}
