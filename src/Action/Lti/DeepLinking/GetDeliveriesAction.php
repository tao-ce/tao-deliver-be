<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Lti\DeepLinking;

use App\DynamicQueryApi\Exception\DynamicQueryApiException;
use App\DynamicQueryApi\Gateway\DynamicQueryApiGateway;
use App\DynamicQueryApi\Serializer\Denormalizer\DeliveryNormalizer;
use App\Responder\SerializerResponder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class GetDeliveriesAction
{
    public function __construct(
        private readonly SerializerResponder $responder,
        private readonly DynamicQueryApiGateway $dynamicQueryApiGateway,
    ) {
    }

    /**
     * @throws DynamicQueryApiException
     */
    public function __invoke(string $tenantId): Response
    {
        $searchResponse = $this->dynamicQueryApiGateway->searchDeliveries([
            'filters' => [
                [
                    'field' => 'tenantId',
                    'type' => 'terms',
                    'values' => [$tenantId],
                ],
            ],
        ]);

        return $this->responder->createJsonResponse(
            ['data' => $searchResponse->getData()],
            Response::HTTP_OK,
            [],
            [DeliveryNormalizer::CONTEXT_VIEW => DeliveryNormalizer::VIEW_LTI_DEEP_LINKING],
        );
    }
}
