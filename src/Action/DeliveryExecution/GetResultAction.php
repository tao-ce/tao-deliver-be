<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\DeliveryExecution;

use App\Qti\Exception\ResultNotFoundException;
use App\Qti\Service\AssessmentResultService;
use App\Response\AssetResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetResultAction
{
    public function __construct(private AssessmentResultService $assessmentResultService)
    {
    }

    public function __invoke(string $id, string $tenantId): Response
    {
        try {
            try {
                $resultPath = "{$tenantId}/$id";
                $assessmentResultMetadata = $this->assessmentResultService->getAssessmentResultMetadata($resultPath);
                $id = $resultPath;
            } catch (ResultNotFoundException) {
                $assessmentResultMetadata = $this->assessmentResultService->getAssessmentResultMetadata($id);
            }

            return new AssetResponse(
                $this->assessmentResultService->getStreamedAssessmentResult($id),
                $assessmentResultMetadata->getMimeType(),
                $assessmentResultMetadata->getTimestamp(),
                $assessmentResultMetadata->getSize(),
            );
        } catch (ResultNotFoundException $exception) {
            throw new NotFoundHttpException(
                $exception->getMessage(),
                $exception,
            );
        }
    }
}
