<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message\DataPolicy;

use App\Messenger\Message\NormalizableInterface;
use App\Validator\Messenger\DataPolicy\ValidationRequestMessageValidator;
use Symfony\Component\Validator\Validation;

readonly class ValidationRequestMessage implements NormalizableInterface
{
    public function __construct(
        public string $type,
        public string $policyId,
        public string $policyVersion,
        public string $tenantId,
        public string $ownerApp,
        public string $userId,
    ) {
    }

    public static function fromArray(array $raw): static
    {
        $validator = new ValidationRequestMessageValidator(Validation::createValidator());
        $parsed = $validator->validateAndNormalize($raw);
        $data = $parsed['body'];

        return new self(
            $parsed['type'],
            $data['policyId'],
            $data['policyVersion'],
            $data['tenantId'],
            $data['ownerApp'],
            $data['dataSubjectRawId'],
        );
    }
}
