<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Delivery;

use App\Domain\Delivery\Model\Delivery;
use App\Generator\UrlGenerator;

readonly class GenerateDeliveryLtiLaunchUrlService
{
    public function __construct(private UrlGenerator $urlGenerator)
    {
    }

    public function generate(Delivery $delivery): string
    {
        return $this->urlGenerator->generate('api_v1_launch_lti_1p3', ['deliveryId' => $delivery->getId()]);
    }
}
