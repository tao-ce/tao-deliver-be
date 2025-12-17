<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\DeliveryExecution;

use Symfony\Component\HttpFoundation\Request;

class SynchronizeDeliveryExecutionRequestValidator extends DeliveryExecutionAwareRequestValidator
{
    protected function getRequestData(Request $request): array
    {
        return $this->extractRequestJsonContent($request);
    }

    /**
     * @inheritDoc
     */
    protected function getRequestValidationConstraint(): array
    {
        return $this->getDeliveryExecutionConstraints();
    }
}
