<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Normalizer;

use App\Domain\Publication\Model\Publication;
use Exception;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverData;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;
use OAT\Bundle\DocumentManagerBundle\Normalizer\AbstractDocumentNormalizer;

class PublicationNormalizer extends AbstractDocumentNormalizer
{
    /**
     * @throws DocumentNormalizerException
     */
    public function denormalizeDocument(DocumentDriverDataInterface $documentData, string $documentClass): DocumentInterface
    {
        try {
            $publicationData = $documentData->getData();

            $publication = new Publication(
                $documentData->getId(),
                $publicationData['tenantId'],
                $publicationData['packagePath'],
                $publicationData['packageRef'],
                $publicationData['packageConfiguration'],
                $publicationData['reports'],
                $publicationData['status'],
                $publicationData['deliveryId'],
                $publicationData['locale'],
            );
            $publication->clearUpdates();

            return $publication;
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                sprintf('Cannot denormalize publication: %s', $exception->getMessage()),
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
                    'tenantId' => $document->getTenantId(),
                    'packagePath' => $document->getPackagePath(),
                    'packageRef' => $document->getPackageRef(),
                    'packageConfiguration' => $document->getPackageConfiguration(),
                    'reports' => $document->getReports(),
                    'status' => $document->getStatus(),
                    'deliveryId' => $document->getDeliveryId(),
                    'locale' => $document->getLocale(),
                ],
            );
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                sprintf('Cannot normalize publication: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    public function supports(DocumentDriverInterface $documentDriver, string $documentClass): bool
    {
        return is_a($documentClass, Publication::class, true);
    }
}
