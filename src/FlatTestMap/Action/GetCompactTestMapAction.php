<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\FlatTestMap\Action;

use App\Domain\Delivery\Model\Delivery;
use App\FlatTestMap\Input\FlatMapSearchInput;
use App\FlatTestMap\Service\FlatMapService;
use App\Responder\SerializerResponder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class GetCompactTestMapAction
{
    public function __construct(
        private SerializerResponder $responder,
        private FlatMapService $service,
    ) {
    }

    public function __invoke(
        Delivery $delivery,
        string $tenantId,
        #[MapQueryParameter]
        ?string $locale = null,
        #[MapQueryParameter]
        array $includeOnlyInteraction = ['extendedTextInteraction'],
        #[MapQueryParameter]
        array $includeOnlyFormat = ['xhtml'],
        #[MapQueryParameter(filter: FILTER_VALIDATE_BOOLEAN)]
        bool $allowMultipleResponseVariables = false,
    ): JsonResponse {
        if ($delivery->getTenantId() !== $tenantId) {
            // Returning a 404 here in order not to expose Delivery IDs to other tenants
            throw new NotFoundHttpException(
                sprintf(
                    'Document class \'%s\' with id \'%s\' not found',
                    $delivery::class,
                    $delivery->getId(),
                ),
            );
        }

        return $this->responder->createJsonResponse(
            $this->service->createFlatMap(
                new FlatMapSearchInput(
                    $delivery,
                    $locale == $delivery->getMainLocale() ? null : $locale,
                    $includeOnlyInteraction,
                    $includeOnlyFormat,
                    $allowMultipleResponseVariables,
                ),
            ),
        );
    }
}
