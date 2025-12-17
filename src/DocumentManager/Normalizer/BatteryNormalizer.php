<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Normalizer;

use App\Domain\Battery\Model\Battery;
use Exception;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverData;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;
use OAT\Bundle\DocumentManagerBundle\Normalizer\AbstractDocumentNormalizer;

class BatteryNormalizer extends AbstractDocumentNormalizer
{
    public function __construct(private readonly BatteryDeliveriesNormalizer $batteryDeliveriesNormalizer)
    {
    }

    /**
     * @throws DocumentNormalizerException
     */
    public function denormalizeDocument(
        DocumentDriverDataInterface $documentData,
        string $documentClass,
    ): DocumentInterface {
        try {
            $data = $documentData->getData();

            $battery = new Battery(
                id: $documentData->getId(),
                tenantId: $data['tenantId'],
                name: $data['name'] ?? '',
                description: $data['description'] ?? '',
                status: $data['status'] ?? Battery::STATUS_INACTIVE,
                mode: $data['mode'] ?? Battery::MODE_RANDOM_DELIVERY,
                deliveries: $this->batteryDeliveriesNormalizer->denormalize($data['deliveries'] ?? []),
            );
            $battery->clearUpdates();

            return $battery;
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                message: sprintf(
                    'Cannot denormalize battery:%s Error: %s',
                    $documentData->getId(),
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }

    /**
     * @param Battery $document
     *
     * @throws DocumentNormalizerException
     */
    public function normalizeDocument(DocumentInterface $document): DocumentDriverDataInterface
    {
        try {
            $normalizedBattery = json_decode(json_encode($document), true);
            unset($normalizedBattery['id']);

            return new DocumentDriverData($document->getId(), $normalizedBattery);
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                sprintf('Cannot normalize battery: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    public function supports(DocumentDriverInterface $documentDriver, string $documentClass): bool
    {
        return is_a($documentClass, Battery::class, true);
    }
}
