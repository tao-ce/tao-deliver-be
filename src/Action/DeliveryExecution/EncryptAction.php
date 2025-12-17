<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\DeliveryExecution;

use App\Serializer\Normalizer\DeliveryExecutionNormalizer;
use App\Service\DeliveryExecution\Contract\DeliveryExecutionEncryptionServiceInterface;
use App\Service\DeliveryExecution\Contract\RepositoryAwareDeliveryExecutionServiceInterface;
use App\Validator\DeliveryExecution\EncryptDeliveryExecutionSessionRequestValidator;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EncryptAction
{
    public function __construct(
        private readonly RepositoryAwareDeliveryExecutionServiceInterface $deliveryExecutionService,
        private readonly EncryptDeliveryExecutionSessionRequestValidator $requestValidator,
        private readonly DeliveryExecutionEncryptionServiceInterface $encryptDeliveryExecutionService,
        private readonly DeliveryExecutionNormalizer $deliveryExecutionNormalizer,
    ) {
    }

    public function __invoke(Request $request, string $tenantId): JsonResponse
    {
        $requestData = $this->requestValidator->getValidatedRequestParameters($request);
        $deliveryExecutionId = $this->requestValidator->extractDeliveryExecutionIdFromValidatedData($requestData);
        $this->validateTenantId($tenantId, $deliveryExecutionId);

        try {
            $deliveryExecution = $this->deliveryExecutionService->findDeliveryExecutionOrFail($deliveryExecutionId);
        } catch (DocumentNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        $encryptionKey = $this->requestValidator->extractEncryptionKeyFromValidatedData($requestData);
        $encryptedDeliveryExecution = $this->encryptDeliveryExecutionService->encrypt(
            $deliveryExecution,
            $encryptionKey,
        );

        return new JsonResponse($this->deliveryExecutionNormalizer->normalize($encryptedDeliveryExecution));
    }

    private function validateTenantId(string $tenantId, string $deliverExecutionId): void
    {
        if (!str_contains($deliverExecutionId, $tenantId)) {
            throw new AccessDeniedException(
                sprintf(
                    'Delivery execution [%s] does not belong provided tenant [%s]',
                    $deliverExecutionId,
                    $tenantId,
                ),
            );
        }
    }
}
