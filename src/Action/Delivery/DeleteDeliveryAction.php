<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\Delivery;

use App\Domain\Delivery\Model\Delivery;
use App\Service\Delivery\DeleteDeliveryService;
use Symfony\Component\HttpFoundation\Response;

class DeleteDeliveryAction
{
    public function __construct(private readonly DeleteDeliveryService $deleteDeliveryService)
    {
    }

    public function __invoke(Delivery $delivery): Response
    {
        $this->deleteDeliveryService->softDelete($delivery);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
