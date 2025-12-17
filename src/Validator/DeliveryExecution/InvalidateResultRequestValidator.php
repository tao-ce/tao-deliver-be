<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\DeliveryExecution;

use App\Validator\AbstractRequestValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class InvalidateResultRequestValidator extends AbstractRequestValidator
{
    protected function getRequestData(Request $request): array
    {
        return $this->extractRequestJsonContent($request);
    }

    protected function getRequestValidationConstraint(): Constraint
    {
        return new Collection([
            'invalidatedBy' => [
                new NotBlank(),
                new Type(['type' => 'string']),
            ],
        ]);
    }
}
