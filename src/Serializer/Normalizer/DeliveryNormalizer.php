<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Domain\Delivery\Model\Delivery;
use App\Service\Delivery\GenerateDeliveryLtiLaunchUrlService;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DeliveryNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly GenerateDeliveryLtiLaunchUrlService $ltiLaunchUrlGenerator,
    ) {
    }

    /**
     * @param Delivery $object
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        return [
            'id' => $object->getId(),
            'tenantId' => $object->getTenantId(),
            'draftId' => $object->getDraftId(),
            'configuration' => $object->getConfiguration(),
            'compactTestFilePath' => $object->getQtiCompactTestFilePath(),
            'launchUrl' => $this->ltiLaunchUrlGenerator->generate($object),
            'isDisabled' => $object->getIsDisabled(),
            'mainLocale' => $object->getMainLocale(),
            'supportedLocales' => $object->getSupportedLocales(),
            'translations' => $object->getTranslations(),
            'createdAt' => $object->getCreatedAt()->getTimestamp(),
            'version' => '1', // Must remain here for backward compatibility
        ];
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Delivery;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => true,
            Delivery::class => true,
        ];
    }
}
