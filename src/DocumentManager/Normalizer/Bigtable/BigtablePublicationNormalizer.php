<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Normalizer\Bigtable;

use App\Domain\Publication\Model\Publication;
use Exception;
use OAT\Bundle\BigtableDocumentManagerBundle\Driver\BigtableDocumentDriver;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverData;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;

class BigtablePublicationNormalizer extends AbstractBigtableNormalizer
{
    /**
     * @throws DocumentNormalizerException
     */
    public function denormalizeDocument(DocumentDriverDataInterface $documentData, string $documentClass): DocumentInterface
    {
        try {
            $publicationData = $documentData->getData()[self::DATA_COLUMN_FAMILY];
            $publication = new Publication(
                $documentData->getId(),
                $publicationData['tenantId'][0]['value'],
                $publicationData['packagePath'][0]['value'],
                $publicationData['packageRef'][0]['value'],
                json_decode($publicationData['packageConfiguration'][0]['value'], true),
                json_decode($publicationData['reports'][0]['value'], true),
                $publicationData['status'][0]['value'],
                $publicationData['deliveryId'][0]['value'] === '' ? null : $publicationData['deliveryId'][0]['value'],
                $publicationData['locale'][0]['value'] === '' ? null : $publicationData['locale'][0]['value'],
            );

            $publication->clearUpdates();

            return $publication;
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                sprintf(
                    'Cannot denormalize publication with id: "%s" with errorMessage: %s',
                    $documentData->getId(),
                    $exception->getMessage(),
                ),
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
            /** @var Publication $document */
            return new DocumentDriverData(
                $document->getId(),
                [
                    self::DATA_COLUMN_FAMILY => [
                        'tenantId' => $document->getTenantId(),
                        'packagePath' => $document->getPackagePath(),
                        'packageRef' => $document->getPackageRef(),
                        'packageConfiguration' => json_encode($document->getPackageConfiguration()),
                        'reports' => json_encode($document->getReports()),
                        'status' => $document->getStatus(),
                        'deliveryId' => $document->getDeliveryId() ?? '',
                        'locale' => $document->getLocale() ?? '',
                    ],
                ],
                $document->getUpdates(),
            );
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                sprintf(
                    'Cannot normalize publication with id: "%s" with errorMessage: %s',
                    $document->getId(),
                    $exception->getMessage(),
                ),
                0,
                $exception,
            );
        }
    }

    public function supports(DocumentDriverInterface $documentDriver, string $documentClass): bool
    {
        return is_a($documentClass, Publication::class, true) && $documentDriver instanceof BigtableDocumentDriver;
    }
}
