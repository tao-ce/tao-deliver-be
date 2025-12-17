<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2020 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\CsvResult;

use App\Validator\AbstractRequestValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

class CreateCsvResultActionValidator extends AbstractRequestValidator
{
    public const PARAM_LIMIT = 'limit';

    protected function getRequestData(Request $request): array
    {
        return [
            self::PARAM_LIMIT => $request->query->get(self::PARAM_LIMIT),
        ];
    }

    protected function getRequestValidationConstraint()
    {
        return new Collection([
            self::PARAM_LIMIT => new Optional([
                new Type(['type' => 'string']),
                new GreaterThan(['value' => 0]),
                new LessThan(['value' => PHP_INT_MAX]),
            ]),
        ]);
    }
}
