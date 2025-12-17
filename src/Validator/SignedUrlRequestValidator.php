<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class SignedUrlRequestValidator extends AbstractRequestValidator
{
    public const PATH_PARAMETER = 'path';
    protected function getRequestData(Request $request): array
    {
        return [
            self::PATH_PARAMETER => $request->query->get(self::PATH_PARAMETER),
        ];
    }

    /**
     * @return Constraint|Constraint[]
     */
    protected function getRequestValidationConstraint()
    {
        return new Collection(
            [
                self::PATH_PARAMETER => [new NotBlank(), new Type(['type' => 'string'])],
            ],
        );
    }
}
