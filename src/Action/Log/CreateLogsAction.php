<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Log;

use App\Registry\LoggerRegistry;
use App\Validator\Log\CreateLogsActionRequestValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CreateLogsAction
{
    /** @var CreateLogsActionRequestValidator */
    private $validator;

    /** @var LoggerRegistry */
    private $loggerRegistry;

    public function __construct(
        CreateLogsActionRequestValidator $validator,
        LoggerRegistry $loggerRegistry,
    ) {
        $this->validator = $validator;
        $this->loggerRegistry = $loggerRegistry;
    }

    public function __invoke(Request $request): Response
    {
        $requestData = $this->validator->getValidatedRequestParameters($request);

        foreach ($requestData as $requestDataRow) {
            $logger = $this->loggerRegistry->getLoggerForChannel(
                $requestDataRow[CreateLogsActionRequestValidator::FIELD_TYPE],
            );

            $logger->log(
                $requestDataRow[CreateLogsActionRequestValidator::FIELD_LEVEL],
                $requestDataRow[CreateLogsActionRequestValidator::FIELD_MESSAGE],
                $requestDataRow[CreateLogsActionRequestValidator::FIELD_CONTEXT] ?? [],
            );
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
