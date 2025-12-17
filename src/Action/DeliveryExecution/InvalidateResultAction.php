<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\DeliveryExecution;

use App\Action\DeliveryExecution\Traits\DeliveryExecutionActionProcessorTrait;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Responder\SerializerResponder;
use App\Service\DeliveryExecution\DeliveryExecutionInvalidationService;
use App\Validator\DeliveryExecution\InvalidateResultRequestValidator;
use App\Validator\Exception\RequestValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

readonly class InvalidateResultAction
{
    use DeliveryExecutionActionProcessorTrait;

    public function __construct(
        private SerializerResponder $responder,
        private DeliveryExecutionInvalidationService $invalidationService,
        private InvalidateResultRequestValidator $validator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DeliveryExecution $deliveryExecution, Request $request): Response
    {
        $requestData = $this->validator->getValidatedRequestParameters($request);

        if (!$deliveryExecution->isStateFinal()) {
            return $this->createFailResponse(
                new RequestValidationException(
                    'Cannot invalidate results for delivery execution that is not finished',
                    Response::HTTP_BAD_REQUEST,
                ),
                [[
                    'success' => false,
                    'error' => 'Cannot invalidate results for delivery execution that is not finished',
                    'currentStatus' => $deliveryExecution->getStatus(),
                    'allowedStatuses' => ['closed', 'terminated'],
                ]],
            );
        }

        if ($deliveryExecution->isResultInvalidated()) {
            $this->invalidationService->triggerDataStoreSync($deliveryExecution);

            $this->logger->info(
                sprintf(
                    'DataStore sync triggered for already invalidated delivery execution %s',
                    $deliveryExecution->getId(),
                ),
            );

            return $this->createSuccessResponse([[
                'success' => true,
                'message' => 'DataStore sync triggered for already invalidated delivery execution',
                'deliveryExecutionId' => $deliveryExecution->getId(),
                'invalidatedBy' => $deliveryExecution->getinvalidation()?->getInvalidatedBy(),
                'invalidatedAt' => $deliveryExecution->getinvalidation()?->getInvalidatedAt()->format('c'),
                'syncTriggered' => true,
            ]]);
        }

        $userLogin = $requestData['invalidatedBy'];

        $this->invalidationService->invalidate($deliveryExecution, $userLogin);

        $this->logger->info(
            sprintf(
                'Delivery execution %s result invalidated by user %s',
                $deliveryExecution->getId(),
                $userLogin,
            ),
        );

        return $this->createSuccessResponse([[
            'success' => true,
            'message' => 'Delivery execution result has been invalidated',
            'deliveryExecutionId' => $deliveryExecution->getId(),
            'invalidatedBy' => $userLogin,
            'invalidatedAt' => $deliveryExecution->getinvalidation()?->getInvalidatedAt()->format('c'),
        ]]);
    }

}
