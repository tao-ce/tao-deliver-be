<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\DeliveryExecution;

use App\Domain\DeliveryExecution\Model\DeliveryExecutionActorRole;
use App\Validator\AbstractRequestValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraint;

class DeliveryExecutionLogActionValidator extends AbstractRequestValidator
{
    private const ALLOWED_ISSUER = [
        DeliveryExecutionActorRole::ROLE_DELIVER_FE->value,
        DeliveryExecutionActorRole::ROLE_REAL_TIME_SERVICE->value,
    ];

    protected function getRequestData(Request $request): array
    {
        return $this->extractRequestJsonContent($request);
    }

    /**
     * @return array|Constraint[]
     */
    protected function getRequestValidationConstraint()
    {
        return [
            new NotBlank(),
            new Collection([
                'issuer' => [
                    new NotBlank(),
                    new Type(['type' => 'string']),
                    new Choice(self::ALLOWED_ISSUER),
                ],
                'reason' => [
                    new NotBlank(),
                    new Type(['type' => 'string']),
                ],
            ]),
        ];
    }
}
