<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

namespace App\Qti\Service\Contract\Exceptions;

use App\Qti\Service\Contract\ArgumentOutcomeVariableInterface;
use qtism\common\enums\BaseType;
use qtism\common\enums\Cardinality;
use qtism\runtime\common\Variable;
use RuntimeException;

class OutcomeVariableParametersMismatch extends RuntimeException
{
    public static function createForBaseTypeMismatch(
        Variable $sessionOutcomeVariable,
        ArgumentOutcomeVariableInterface $inputOutcomeVariable,
    ): self {
        return new self(
            sprintf(
                'Cannot modify outcome variable "%s": expected base type "%s", got "%s".',
                $inputOutcomeVariable->getId(),
                BaseType::getNameByConstant($sessionOutcomeVariable->getBaseType()),
                $inputOutcomeVariable->getBaseType(),
            ),
        );
    }

    public static function createForCardinalityMismatch(
        Variable $sessionOutcomeVariable,
        ArgumentOutcomeVariableInterface $inputOutcomeVariable,
    ): self {
        return new self(
            sprintf(
                'Cannot modify outcome variable "%s": expected cardinality "%s", got "%s".',
                $inputOutcomeVariable->getId(),
                Cardinality::getNameByConstant($sessionOutcomeVariable->getCardinality()),
                $inputOutcomeVariable->getCardinality(),
            ),
        );
    }
}
