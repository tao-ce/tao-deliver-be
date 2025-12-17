<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Delivery;

use App\Domain\Delivery\Model\Delivery;
use App\Responder\SerializerResponder;
use App\Service\Delivery\GenerateDeliveryStatisticsService;
use Symfony\Component\HttpFoundation\JsonResponse;

class GetDeliveryStatisticsAction
{
    /** @var GenerateDeliveryStatisticsService */
    private $generator;

    /** @var SerializerResponder */
    private $responder;

    public function __construct(GenerateDeliveryStatisticsService $generator, SerializerResponder $responder)
    {
        $this->generator = $generator;
        $this->responder = $responder;
    }

    public function __invoke(Delivery $delivery): JsonResponse
    {
        return $this->responder->createJsonResponse([
            'data' => $this->generator->generate($delivery),
        ]);
    }
}
