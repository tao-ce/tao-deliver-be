<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Messenger\Message\DataPolicy;

use App\Messenger\Message\NormalizableInterface;
use App\Validator\Messenger\DataPolicy\RemovalRequestMessageValidator;
use Symfony\Component\Validator\Validation;

readonly class RemovalRequestMessage implements NormalizableInterface
{
    public function __construct(
        public string $type,
        public string $policyId,
        public string $policyVersion,
        public string $userId,
        public string $tenantId,
        public string $uniqueId,
        public string $storageType,
        public string $ownerApp,
        public array $deliveryExecutionIds,
        public string $name,
    ) {
    }

    public static function fromArray(array $raw): static
    {
        $validator = new RemovalRequestMessageValidator(Validation::createValidator());
        $normalized = $validator->validateAndNormalize($raw);

        $type = $normalized['type'];
        $body = $normalized['body'];
        $metadata = $normalized['metadata'];

        return new self(
            $type,
            $body['policyId'],
            $body['policyVersion'],
            $body['dataSubjectRawId'],
            $body['tenantId'],
            $body['uniqueId'],
            $body['storageType'],
            $body['ownerApp'],
            $metadata['deliveryExecutions'],
            $body['name'],
        );
    }
}
