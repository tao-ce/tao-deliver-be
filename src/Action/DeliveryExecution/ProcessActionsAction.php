<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\DeliveryExecution;

use App\Action\DeliveryExecution\Traits\DeliveryExecutionActionProcessorTrait;
use App\Responder\SerializerResponder;
use App\Security\Contract\DeliveryExecutionSessionController;
use App\TestRunner\ActionProcessor\Handler\ActionProcessorHandler;
use App\Validator\DeliveryExecution\ProcessActionsActionRequestValidator;
use App\Validator\Exception\RequestValidationException;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

readonly class ProcessActionsAction implements DeliveryExecutionSessionController
{
    use DeliveryExecutionActionProcessorTrait;

    public function __construct(
        private SerializerResponder $responder,
        private ActionProcessorHandler $actionProcessorHandler,
        private ProcessActionsActionRequestValidator $validator,
    ) {
    }

    /**
     * @throws DocumentNotFoundException
     */
    public function __invoke(Request $request, string $id): JsonResponse
    {
        try {
            $requestData = $this->validator->getValidatedRequestParameters($request);

            // In Phase 1 we support only one channel (the first one)
            $firstChannelData = current($requestData);

            $actionResponses = $this->actionProcessorHandler->handle($id, $firstChannelData['message']['actions']);
        } catch (RequestValidationException $exception) {
            return $this->createFailResponse($exception, $actionResponses ?? []);
        }

        return $this->createSuccessResponse($actionResponses);
    }
}
