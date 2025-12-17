<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Model\ExtraStateData\SubData;

use App\Domain\DeliveryExecution\Model\ExtraStateData\PlagiarismReport;
use App\Helper\Date;

trait PlagiarismReportTrait
{
    private array $plagiarismReports = [];

    protected function fromArrayPlagiarismReports(array $data): array
    {
        return array_map(static function (array|PlagiarismReport $report) {
            if ($report instanceof PlagiarismReport) {
                return $report;
            }
            return new PlagiarismReport(
                $report['provider'],
                $report['id'],
                Date::createFromDefaultFormat($report['createdAt']),
                $report['itemId'],
                $report['responseId'],
                $report['status'],
                $report['href'],
            );
        }, $data);
    }

    protected function toArrayPlagiarismReports(): array
    {
        return array_map(static function (PlagiarismReport $report) {
            return [
                'provider' => $report->getProvider(),
                'id' => $report->getId(),
                'createdAt' => $report->getCreatedAt()->format(Date::DEFAULT_FORMAT),
                'itemId' => $report->getItemId(),
                'responseId' => $report->getResponseId(),
                'status' => $report->getStatus(),
                'href' => $report->getHref(),
            ];
        }, $this->plagiarismReports);
    }

    public function withPlagiarismReport(PlagiarismReport $report): self
    {
        $deliveryExecutionExtraStateData = clone $this;
        $foundYounger = false;
        /** @var PlagiarismReport $existing */
        foreach ($deliveryExecutionExtraStateData->plagiarismReports as $id => $existing) {
            if (
                $existing->getProvider() === $report->getProvider()
                && $existing->getItemId() === $report->getItemId()
                && $existing->getResponseId() === $report->getResponseId()
            ) {
                if ($existing->getCreatedAt()->gt($report->getCreatedAt())) {
                    $foundYounger = true;
                } else {
                    unset($deliveryExecutionExtraStateData->plagiarismReports[$id]);
                }
            }
        }
        if (!$foundYounger) {
            $deliveryExecutionExtraStateData->plagiarismReports[$report->getId()] = $report;
        }

        return $deliveryExecutionExtraStateData;
    }

    public function getPlagiarismReports(): array
    {
        return $this->plagiarismReports;
    }
}
