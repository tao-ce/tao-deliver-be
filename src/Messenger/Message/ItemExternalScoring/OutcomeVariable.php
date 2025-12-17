<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message\ItemExternalScoring;

use App\Qti\Service\Contract\ArgumentOutcomeVariableInterface;
use qtism\common\enums\BaseType;

class OutcomeVariable implements ArgumentOutcomeVariableInterface
{
    private const APPLICABLE_TYPES = [BaseType::INTEGER, BaseType::FLOAT];

    private string $baseType;
    private string $cardinality;
    private string $identifier;
    private string $value;

    public static function fromArray(array $input): self
    {
        $var = new self();

        $var->baseType = $input['baseType'];
        $var->cardinality = $input['cardinality'];
        $var->identifier = $input['identifier'];
        $var->value = $input['value'];

        return $var;
    }

    public function getId(): string
    {
        return $this->identifier;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getBaseType(): string
    {
        return $this->baseType;
    }

    public function getCardinality(): string
    {
        return $this->cardinality;
    }

    public function isApplicable(): bool
    {
        return in_array(
            BaseType::getConstantByName($this->getBaseType()),
            self::APPLICABLE_TYPES,
            true,
        );
    }
}
