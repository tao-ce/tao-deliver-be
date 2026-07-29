<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Items;

use App\Action\DeliveryExecution\Traits\DeliveryExecutionActionProcessorTrait;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Responder\SerializerResponder;
use App\Security\Contract\DeliveryExecutionSessionController;
use App\TestRunner\ActionProcessor\GetItemActionProcessor;
use App\Validator\Exception\RequestValidationException;
use App\Validator\Items\GetInitItemsRequestValidator;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Request;

readonly class GetInitItems implements DeliveryExecutionSessionController
{
    use DeliveryExecutionActionProcessorTrait;

    public function __construct(
        private SerializerResponder $responder,
        private GetItemActionProcessor $actionProcessor,
        private GetInitItemsRequestValidator $validator,
    ) {
    }

    public function __invoke(Request $request, string $id, string $tenantId)
    {
        try {
            $data = $this->validator->getValidatedRequestParameters($request);
            $deliveryExecution = $this->createDeliveryExecutionStub($id, $tenantId, $data['locale'] ?? null);
            $responses = array_map(
                fn(array $actionParameters): array => $this->actionProcessor->process(
                    $deliveryExecution,
                    $actionParameters,
                ),
                $data['items'],
            );
        } catch (RequestValidationException $exception) {
            return $this->createFailResponse($exception, $responses ?? []);
        }

        return $this->createSuccessResponse($responses);
    }

    public function createDeliveryExecutionStub(string $id, string $tenantId, ?string $locale = null): DeliveryExecution
    {
        return new DeliveryExecution(
            implode(
                DeliveryExecution::DOCUMENT_KEY_DELIMITER,
                [strrev(DeliveryExecution::ATTEMPT_ID), $id, DeliveryExecution::ATTEMPT_ID, $tenantId],
            ),
            $id,
            $tenantId,
            Carbon::now(),
            [
                'result_id' => DeliveryExecution::DRY_RUN_ATTEMPT_ID,
                'user_id' => DeliveryExecution::ATTEMPT_ID,
                'user_name' => DeliveryExecution::ATTEMPT_ID,
            ],
            '',
            locale: $locale,
        );
    }
}
