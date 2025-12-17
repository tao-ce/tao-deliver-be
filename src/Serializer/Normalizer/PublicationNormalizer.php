<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Serializer\Normalizer;

use App\Domain\Publication\Model\Publication;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PublicationNormalizer implements NormalizerInterface
{
    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        /** @var Publication $object */
        return [
            'id' => $object->getId(),
            'status' => $object->getStatus(),
            'url' => $this->generatePublicationUrl($object),
            'tenantId' => $object->getTenantId(),
            'deliveryId' => $object->getDeliveryId(),
            'reports' => $object->getReports(),
            'locale' => $object->getLocale(),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Publication;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => true,
            Publication::class => true,
        ];
    }

    private function generatePublicationUrl(Publication $publication): string
    {
        return $this->urlGenerator->generate(
            'api_v1_get_publication',
            ['id' => $publication->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
