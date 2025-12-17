<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\Delivery;

use App\Validator\AbstractRequestValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class UpdateDeliveryRequestValidator extends AbstractRequestValidator
{
    protected function getRequestData(Request $request): array
    {
        $requestParams = $this->extractRequestJsonContent($request);

        return [
            'configuration' => [
                'label' => $requestParams['configuration']['label'] ?? null,
                'status' => $requestParams['configuration']['status'] ?? null,
                'metadata' => $requestParams['configuration']['metadata'] ?? null,
                'availabilityDate' => $requestParams['configuration']['availabilityDate'] ?? null,
                'expiryDate' => $requestParams['configuration']['expiryDate'] ?? null,
            ],
        ];
    }

    protected function getRequestValidationConstraint(): Constraint
    {
        return new Collection(
            [
                'configuration' =>
                    new Collection(
                        [
                            'label' => [new NotBlank(), new Type(['type' => 'string'])],
                            'status' => [new Type(['type' => 'bool'])],
                            'metadata' => [
                                new NotBlank(['allowNull' => true]),
                                new All([new NotBlank(), new All([new Type(['type' => 'string'])])]),
                            ],
                            'availabilityDate' => [new Type(['type' => 'integer'])],
                            'expiryDate' => [new Type(['type' => 'integer'])],
                        ],
                    ),
            ],
        );
    }
}
