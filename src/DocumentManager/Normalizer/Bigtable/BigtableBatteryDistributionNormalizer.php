<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Normalizer\Bigtable;

use App\DocumentManager\Normalizer\BatteryDeliveriesNormalizer;
use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDistribution;
use Exception;
use OAT\Bundle\BigtableDocumentManagerBundle\Driver\BigtableDocumentDriver;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverData;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;

class BigtableBatteryDistributionNormalizer extends AbstractBigtableNormalizer
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
            $data = $documentData->getData()[self::DATA_COLUMN_FAMILY];

            $battery = new BatteryDistribution(
                $documentData->getId(),
                $data['userId'][0]['value'],
                $this->denormalizeBattery(json_decode($data['battery'][0]['value'], true)),
                isset($data['locale'][0]['value']) && $data['locale'][0]['value'] !== ''
                    ? $data['locale'][0]['value']
                    : null,
            );
            $battery->clearUpdates();

            return $battery;
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                sprintf('Cannot denormalize battery distribution: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    /**
     * @throws DocumentNormalizerException
     */
    public function normalizeDocument(DocumentInterface $document): DocumentDriverDataInterface
    {
        try {
            /** @var BatteryDistribution $document */
            return new DocumentDriverData(
                $document->getId(),
                [
                    self::DATA_COLUMN_FAMILY => [
                        'userId' => $document->userId,
                        'battery' => json_encode($document->battery),
                        'locale' => $document->getLocale() ?? '',
                    ],
                ],
                $document->getUpdates(),
            );
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                sprintf('Cannot normalize battery distribution: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    public function supports(DocumentDriverInterface $documentDriver, string $documentClass): bool
    {
        return is_a($documentClass, BatteryDistribution::class, true) && $documentDriver instanceof BigtableDocumentDriver;
    }

    private function denormalizeBattery(array $batteryData): Battery
    {
        $battery = new Battery(
            id: $batteryData['id'],
            tenantId: $batteryData['tenantId'],
            name: $batteryData['name'],
            description: $batteryData['description'],
            status: $batteryData['status'],
            mode: $batteryData['mode'],
            deliveries: $this->batteryDeliveriesNormalizer->denormalize($batteryData['deliveries']),
        );
        $battery->clearUpdates();

        return $battery;
    }
}
