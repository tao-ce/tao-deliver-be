<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\DeliveryExecution;

use App\Validator\AbstractRequestValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraint;

class LogActionEventsProcessorValidator extends AbstractRequestValidator
{
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
                'events' => [
                    new NotBlank(),
                    new All([
                        new Collection([
                            'itemIdentifier' => [
                                new Optional(new Type(['type' => 'string'])),
                            ],
                            'domEventType' => [
                                new NotBlank(),
                                new Type(['type' => 'string']),
                            ],
                            'responseIdentifier' => [
                                new Optional(new Type(['type' => 'string'])),
                            ],
                            'metadata' => [
                                new Optional(new Type(['type' => 'array'])),
                            ],
                        ]),
                    ]),
                ],
            ]),
        ];
    }
}
