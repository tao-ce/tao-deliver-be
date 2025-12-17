<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Validator\Lti\DeepLinking;

use App\Validator\AbstractRequestValidator;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Type;

class SubmitDeepLinksActionRequestValidator extends AbstractRequestValidator
{
    public const PARAM_DELIVERIES = 'deliveries';
    public const PARAM_BATTERIES = 'batteries';

    protected function getRequestData(Request $request): array
    {
        try {
            $requestParams = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $exception) {
            throw new BadRequestHttpException(
                sprintf(
                    'Failed to decode request JSON body: %s',
                    $exception->getMessage(),
                ),
                $exception,
            );
        }

        return [
            self::PARAM_DELIVERIES => $requestParams[self::PARAM_DELIVERIES] ?? [],
            self::PARAM_BATTERIES => $requestParams[self::PARAM_BATTERIES] ?? [],
        ];
    }

    protected function getRequestValidationConstraint(): Constraint
    {
        return new Collection([
            self::PARAM_DELIVERIES => new All([
                new Type(['type' => 'string']),
            ]),
            self::PARAM_BATTERIES => new All([
                new Type(['type' => 'string']),
            ]),
        ]);
    }
}
