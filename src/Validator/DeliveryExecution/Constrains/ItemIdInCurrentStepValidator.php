<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\DeliveryExecution\Constrains;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ItemIdInCurrentStepValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     *
     * @param ItemIdInCurrentStep $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if ($constraint->currentSessionItemId === $value) {
            return ;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ value }}', $this->formatValue($value))
            ->setParameter('{{ currentItemId }}', $constraint->currentSessionItemId)
            ->setCode(ItemIdInCurrentStep::NO_ITEM_IN_SESSION)
            ->addViolation();
    }
}
