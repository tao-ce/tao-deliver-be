<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\Items;

use App\TestRunner\Service\GetItemService;
use App\Validator\AbstractRequestValidator;
use App\Validator\Locale\LocaleValidator;
use Carbon\Carbon;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class GetInitItemsRequestValidator extends AbstractRequestValidator
{
    public function __construct(
        ValidatorInterface $validator,
        private readonly LocaleValidator $localeValidator,
    ) {
        parent::__construct($validator);
    }

    public function getValidateRequestData(array $requestData): array
    {
        $now = Carbon::now();
        $input = parent::getValidateRequestData($requestData);

        return [
            'items' => array_map(static fn(string $itemId): array => [
                'id' => "getItem_$now",
                'name' => 'getItem',
                'parameters' => [
                    'itemIdentifier' => $itemId,
                ],
                'timestamp' => $now->getTimestamp(),
                'requestDataType' => GetItemService::DATA_TYPE_STATIC,
            ], $input['itemId']),
            'locale' => $input['locale'] ?? null,
        ];
    }

    protected function getRequestData(Request $request): array
    {
        return $request->query->all();
    }

    protected function getRequestValidationConstraint(): array
    {
        return [
            new Constraints\NotBlank(),
            new Constraints\Collection([
                'itemId' => [
                    new All([new NotBlank(), new Type(['type' => 'string'])]),
                ],
                'locale' => new Optional([
                    new Type('string'),
                    new Callback([$this, 'validateLocale']),
                ]),
            ]),
        ];
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
