<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Responder\SerializerResponder;
use App\Security\Contract\DeliveryExecutionSessionController;
use App\Service\DeliveryExecution\DeliveryExecutionCommentService;
use App\Service\DeliveryExecution\DeliveryExecutionService;
use App\Validator\DeliveryExecution\SetAnnotationCommentActionValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SetAnnotationCommentAction implements DeliveryExecutionSessionController
{
    public function __construct(
        private readonly SerializerResponder $responder,
        private readonly SetAnnotationCommentActionValidator $requestValidator,
        private readonly DeliveryExecutionCommentService $deliveryExecutionCommentService,
        private readonly DeliveryExecutionService $deliveryExecutionService,
    ) {
    }

    public function __invoke(Request $request, DeliveryExecution $deliveryExecution): JsonResponse
    {
        $this->authorize($deliveryExecution);

        $data = $this->requestValidator->getValidatedRequestParameters($request);
        $this->deliveryExecutionCommentService->addItemAnnotationComment(
            $deliveryExecution->originalDeliveryExecution,
            $data['itemId'],
            $data['annotations'],
        );

        $this->deliveryExecutionService->saveDeliveryExecution($deliveryExecution->originalDeliveryExecution);

        return $this->responder->createJsonResponse(
            ['success' => true],
        );
    }

    /**
     * @param DeliveryExecution $deliveryExecution
     */
    private function authorize(DeliveryExecution $deliveryExecution): void
    {
        if (!$deliveryExecution->isReview()) {
            throw new BadRequestHttpException('Delivery execution must be in review state');
        }

        if (!$this->requestValidator->validateTokenRoles()) {
            throw new HttpLtiClaimRoleNotAllowedException(
                sprintf(
                    '[%s] invalid role',
                    $deliveryExecution->getId(),
                ),
                Response::HTTP_FORBIDDEN,
            );
        }
    }
}
