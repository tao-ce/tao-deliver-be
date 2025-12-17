<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\DeliveryExecution;

use App\Validator\AbstractRequestValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\IsNull;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Type;

class ProcessActionsActionRequestValidator extends AbstractRequestValidator
{
    protected function getRequestData(Request $request): array
    {
        return $this->extractRequestJsonContent($request);
    }

    protected function getRequestValidationConstraint(): array
    {
        return [
            new NotBlank(),
            new All([
                new Collection([
                    'channel' => new Optional([
                        new Type('string'),
                    ]),
                    'message' => new Collection([
                        'actions' => [
                            new NotBlank(),
                            new Type('array'),
                            new All([
                                new Collection([
                                    'id' => [
                                        new NotBlank(),
                                        new Length(['min' => 2, 'max' => 50]),
                                        new Regex('/^[\w|_|-]+$/', 'Only alpha characters, _ and - are allowed'),
                                    ],
                                    'name' => [
                                        new NotBlank(),
                                        new Length(['min' => 2, 'max' => 20]),
                                        new Regex('/^[\w|_|-]+$/', 'Only alpha characters, _ and - are allowed'),
                                    ],
                                    'timestamp' => new AtLeastOneOf([
                                        new IsNull(),
                                        new Type('digit'),
                                        new Type('integer'),
                                    ]),
                                    'parameters' => new AtLeastOneOf([
                                        new IsNull(),
                                        new Type('array'),
                                    ]),
                                ]),
                            ]),
                        ],
                    ]),
                ]),
            ]),
        ];
    }
}
