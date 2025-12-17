<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Action\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Security\Contract\DeliveryExecutionSessionController;
use App\Service\Lti\LtiLaunchService;
use Symfony\Component\HttpFoundation\Response;

readonly class AutoReviewAction implements DeliveryExecutionSessionController
{
    public function __construct(private LtiLaunchService $ltiLaunchService)
    {
    }

    public function __invoke(DeliveryExecution $deliveryExecution): Response
    {
        return $this->ltiLaunchService->launchTest(
            $deliveryExecution,
            $deliveryExecution->getLtiLaunchParameters(),
        );
    }
}
