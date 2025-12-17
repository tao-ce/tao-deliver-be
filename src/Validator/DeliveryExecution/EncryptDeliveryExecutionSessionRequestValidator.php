<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Validator\DeliveryExecution;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints;

class EncryptDeliveryExecutionSessionRequestValidator extends DeliveryExecutionAwareRequestValidator
{
    private const ENCRYPTION_KEY_REQUEST_FIELD = 'encryptionKey';
    private const DELIVERY_EXECUTION_ID_REQUEST_FIELD = 'deliveryExecutionId';

    public function extractEncryptionKeyFromValidatedData(array $validatedData): string
    {
        return $validatedData[self::ENCRYPTION_KEY_REQUEST_FIELD];
    }

    public function extractDeliveryExecutionIdFromValidatedData(array $validatedData): string
    {
        return $validatedData[self::DELIVERY_EXECUTION_ID_REQUEST_FIELD];
    }


    protected function getRequestData(Request $request): array
    {
        return $this->extractRequestJsonContent($request);
    }

    /**
     * @inheritDoc
     */
    protected function getRequestValidationConstraint(): array
    {
        return [
            new Constraints\NotBlank(),
            new Constraints\Collection([
                self::ENCRYPTION_KEY_REQUEST_FIELD => new Constraints\Type('string'),
                self::DELIVERY_EXECUTION_ID_REQUEST_FIELD => new Constraints\Type('string'),
            ]),
        ];
    }
}
