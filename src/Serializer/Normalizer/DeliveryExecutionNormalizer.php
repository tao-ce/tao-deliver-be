<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Helper\Date;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DeliveryExecutionNormalizer implements NormalizerInterface
{
    /**
     * @param DeliveryExecution $object
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        return [
            'deliveryExecutionId' => $object->getId(),
            'ltiLaunchParameters' => $object->getLtiLaunchParameters(),
            'qtiSdkEncodedTestSession' => $object->getQtiSdkEncodedTestSession(),
            'extraStateData' => $object->getExtraStateData()->toArray(),
            'reviewInlineComment' => $object->getReviewInlineComment()?->toArray(),
            'status' => $object->getStatus(),
            'locale' => $object->getLocale(),
            'startedAt' => $object->getStartedAt()->format(Date::DEFAULT_FORMAT),
            'finishedAt' => $object->getFinishedAt()?->format(Date::DEFAULT_FORMAT),
            'closeAt' => $object->getCloseAt()?->format(Date::DEFAULT_FORMAT),
            'updatedAt' => $object->getUpdatedAt()?->format(Date::DEFAULT_FORMAT),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof DeliveryExecution;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => true,
            DeliveryExecution::class => true,
        ];
    }
}
