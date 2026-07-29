<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\Messenger\DataPolicy;

use App\Validator\Exception\RequestValidationException;
use Symfony\Component\Validator\Constraints as Assert;

final class RemovalRequestMessageValidator extends RequestMessageValidator
{
    /**
     * Validates and normalizes raw messenger payload.
     *
     * @throws RequestValidationException
     */
    public function validateAndNormalize(array $raw): array
    {
        $raw = parent::validateAndNormalize($raw);

        $body = $raw['body'];
        $violations = $this->validator->validate($body, new Assert\Collection([
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
            'dataSubjectRawId' => new Assert\Required([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'tenantId' => new Assert\Required([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'uniqueId' => new Assert\Required([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'storageType' => new Assert\Required([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'name' => new Assert\Required([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'metadata' => new Assert\Required([
                new Assert\Type('array'),
            ]),
        ], allowExtraFields: true));

        if ($violations->count() > 0) {
            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
            }

            throw new RequestValidationException(implode(', ', $messages));
        }

        $metadata = $body['metadata'];
        $violations = $this->validator->validate($metadata, new Assert\Collection([
            'deliveryExecutions' => new Assert\Required([
                new Assert\Type('array'),
                new Assert\All([
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                ]),
            ]),
        ], allowExtraFields: true));

        if ($violations->count() > 0) {
            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
            }

            throw new RequestValidationException(implode(', ', $messages));
        }

        return [
            'type' => $raw['type'],
            'body' => $body,
            'metadata' => $metadata,
        ];
    }
}
