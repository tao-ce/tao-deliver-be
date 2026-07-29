<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\Messenger\DataPolicy;

use App\Validator\Exception\RequestValidationException;
use Symfony\Component\Validator\Constraints as Assert;

final class ValidationRequestMessageValidator extends RequestMessageValidator
{
    /**
     * Validates and normalizes raw messenger payload.
     *
     * @throws RequestValidationException
     */
    public function validateAndNormalize(array $raw): array
    {
        $raw = parent::validateAndNormalize($raw);
        $violations = $this->validator->validate($raw, new Assert\Collection([
            'type' => new Assert\Required([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'body' => new Assert\Required([
                new Assert\Type('array'),
                new Assert\Collection([
                    'tenantId' => new Assert\Required([
                        new Assert\Type('string'),
                        new Assert\NotBlank(),
                    ]),
                    'dataSubjectRawId' => new Assert\Required([
                        new Assert\Type('string'),
                        new Assert\NotBlank(),
                    ]),
                    'policyId' => new Assert\Required([
                        new Assert\Type('string'),
                        new Assert\NotBlank(),
                    ]),
                    'policyVersion' => new Assert\Required([
                        new Assert\Type('string'),
                        new Assert\NotBlank(),
                    ]),
                    'ownerApp' => new Assert\Required([
                        new Assert\Type('string'),
                        new Assert\NotBlank(),
                    ]),
                ], allowExtraFields: true),
            ]),
        ], allowExtraFields: true));

        if ($violations->count() > 0) {
            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
            }

            throw new RequestValidationException(implode(', ', $messages));
        }

        return $raw;
    }
}
