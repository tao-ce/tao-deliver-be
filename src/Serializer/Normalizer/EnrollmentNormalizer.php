<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Domain\Enrollment\Model\Enrollment;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class EnrollmentNormalizer implements NormalizerInterface
{
    /**
     * @param Enrollment $object
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        return [
            'id' => $object->id,
            'campaignId' => $object->campaignId,
            'campaignName' => $object->campaignName,
            'sessionId' => $object->sessionId,
            'sessionName' => $object->sessionName,
            'sessionTemplateId' => $object->sessionTemplateId,
            'sessionTemplateName' => $object->sessionTemplateName,
            'testCategory' => $object->testCategory,
        ];
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Enrollment::class => true,
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Enrollment;
    }
}
