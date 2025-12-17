<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Domain\Battery\Model\Battery;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class BatteryNormalizer implements NormalizerInterface
{
    /**
     * @param Battery $object
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        return [
            'id' => $object->getId(),
            'tenantId' => $object->tenantId,
            'name' => $object->name,
            'description' => $object->description,
            'status' => $object->status,
            'mode' => $object->mode,
        ];
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => true,
            Battery::class => true, // Supports any other types, but the result is not cacheable
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Battery;
    }
}
