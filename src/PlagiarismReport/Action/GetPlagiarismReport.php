<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\PlagiarismReport\Action;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\PlagiarismReport\Gateway\PlagiarismReportGateway;
use App\Responder\SerializerResponder;
use App\Security\Contract\DeliveryExecutionSessionController;
use Symfony\Component\HttpFoundation\Response;

readonly class GetPlagiarismReport implements DeliveryExecutionSessionController
{
    public function __construct(private SerializerResponder $responder, private PlagiarismReportGateway $gateway)
    {
    }

    public function __invoke(DeliveryExecution $deliveryExecution, string $reportId): Response
    {
        $report = $deliveryExecution->getPlagiarismReport($reportId);
        return $this->responder->createJsonResponse(
            $report->getHref()
                ? ['reportUrl' => $report->getHref()]
                : $this->gateway->getReport($reportId),
        );
    }
}
