<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ActionProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;

class UpActionProcessor extends AbstractActionProcessor
{
    public const ACTION_NAME = 'up';

    public function getActionName(): string
    {
        return self::ACTION_NAME;
    }

    public function process(DeliveryExecution $deliveryExecution, array $actionParameters): array
    {
        return $this->getActionProcessorResponse($actionParameters, []);
    }
}
