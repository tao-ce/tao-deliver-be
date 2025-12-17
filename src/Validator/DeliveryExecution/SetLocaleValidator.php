<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\DeliveryExecution;

use App\Validator\AbstractRequestValidator;
use App\Validator\Locale\LocaleValidator;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SetLocaleValidator extends AbstractRequestValidator
{
    public function __construct(
        private readonly LocaleValidator $localeValidator,
        ValidatorInterface $validator,
    ) {
        parent::__construct($validator);
    }

    protected function getRequestData(Request $request): array
    {
        return $this->extractRequestJsonContent($request);
    }

    protected function getRequestValidationConstraint(): Collection|array|Constraint
    {
        return new Collection([
            'fields' => [
                'locale' => [
                    new NotBlank(),
                    new Type('string'),
                    new Callback([$this, 'validateLocale']),
                ],
            ],
        ]);
    }

    public function validateLocale(mixed $locale, ExecutionContextInterface $context): void
    {
        try {
            $this->localeValidator->validate($locale);
        } catch (InvalidArgumentException $throwable) {
            $context->buildViolation($throwable->getMessage())
                ->addViolation();
        }
    }
}
