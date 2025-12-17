<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\Publication;

use App\Repository\DeliveryRepository;
use App\Validator\AbstractRequestValidator;
use App\Validator\Locale\LocaleValidator;
use InvalidArgumentException;
use JsonException;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreatePublicationRequestValidator extends AbstractRequestValidator
{
    public function __construct(
        private readonly DeliveryRepository $deliveryRepository,
        private readonly LocaleValidator $localeValidator,
        ValidatorInterface $validator,
    ) {
        parent::__construct($validator);
    }

    /**
     * @throws JsonException
     */
    protected function getRequestData(Request $request): array
    {
        $requestParams = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return [
            'package' => $requestParams['package'] ?? '',
            'packageRef' => $requestParams['packageRef'] ?? '',
            'deliveryId' => $requestParams['deliveryId'] ?? null,
            'locale' => $requestParams['locale'] ?? null,
            'translations' => $requestParams['translations'] ?? [],
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
                'package' => [
                    new Callback([$this, 'validatePackageProvided'], payload: 'packageRef'),
                    new Type(['type' => 'string']),
                ],
                'packageRef' => [
                    new Callback([$this, 'validatePackageProvided'], payload: 'package'),
                    new Type(['type' => 'string']),
                ],
                'deliveryId' => [
                    new Callback([$this, 'validateDeliveryId']),
                    new Type(['type' => 'string']),
                ],
                'locale' => [
                    new Callback([$this, 'validateLocale']),
                    new Type(['type' => 'string']),
                ],
                'translations' => [
                    new Callback([$this, 'validateTranslations']),
                    new Type(['type' => 'array']),
                ],
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

    public function validatePackageProvided($value, ExecutionContextInterface $context, $otherFieldName): void
    {
        $requestPayload = $context->getRoot();

        if ($value == '' && $requestPayload[$otherFieldName] == '') {
            $context->buildViolation(
                "Either 'package' should be provided as base64 encoded string, or 'packageRef' should provide the package location in private bucket.",
            )
                ->addViolation();
        }
    }

    public function validateDeliveryId($deliveryId, ExecutionContextInterface $context): void
    {
        if (!$deliveryId) {
            return;
        }

        try {
            $this->deliveryRepository->find($deliveryId);
        } catch (DocumentNotFoundException) {
            return;
        }

        $context->buildViolation('#{{ delivery_id }} delivery already exists.')
            ->setParameter('{{ delivery_id }}', $deliveryId)
            ->addViolation();
    }

    public function validateLocale($locale, ExecutionContextInterface $context): void
    {
        if (is_null($locale)) {
            return;
        }

        try {
            $this->localeValidator->validate($locale);
        } catch (InvalidArgumentException $throwable) {
            $context->buildViolation($throwable->getMessage())
                ->addViolation();
        }
    }


    public function validateTranslations($translations, ExecutionContextInterface $context): void
    {
        foreach ($translations as $locale => $translation) {
            $this->validateLocale($locale, $context);

            if (!is_array($translation) || !isset($translation['packageRef'])) {
                $context->buildViolation("Each translation must be an array with a 'packageRef' key.")
                    ->atPath($locale)
                    ->addViolation();
                continue;
            }

            $this->validatePackageProvided($translation['packageRef'], $context, 'package');
        }
    }
}
