<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\Delivery;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class AttachLanguageToDeliveryRequestValidator
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @throws ValidationFailedException
     */
    public function getValidatedRequestParameters(Request $request): array
    {
        $constraints = new Assert\Collection([
            'package' => new Assert\Optional([
                new Assert\Type('string'),
            ]),
            'packageRef' => new Assert\Optional([
                new Assert\Type('string'),
            ]),
        ]);

        $data = json_decode($request->getContent(), true);
        $violations = $this->validator->validate($data, $constraints);

        if (count($violations) > 0) {
            throw new ValidationFailedException($data, $violations);
        }

        if (empty($data['package']) && empty($data['packageRef'])) {
            $violation = new ConstraintViolation(
                'Either "package" or "packageRef" must be provided.',
                null,
                [],
                $data,
                '',
                null,
            );
            $violationsList = new ConstraintViolationList([$violation]);

            throw new ValidationFailedException($data, $violationsList);
        }

        return $data;
    }
}
