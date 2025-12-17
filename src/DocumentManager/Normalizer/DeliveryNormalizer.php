<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Normalizer;

use App\Domain\Delivery\Model\Delivery;
use Carbon\Carbon;
use Exception;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverData;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;
use OAT\Bundle\DocumentManagerBundle\Normalizer\AbstractDocumentNormalizer;

class DeliveryNormalizer extends AbstractDocumentNormalizer
{
    /**
     * @throws DocumentNormalizerException
     */
    public function denormalizeDocument(DocumentDriverDataInterface $documentData, string $documentClass): DocumentInterface
    {
        try {
            $deliveryData = $documentData->getData();

            $delivery = new Delivery(
                $documentData->getId(),
                $deliveryData['tenantId'],
                Carbon::createFromTimestamp($deliveryData['createdAt'])->toDateTime(),
                $deliveryData['compactTestFilePath'],
                $deliveryData['configuration'] ?? [],
                $deliveryData['qtiItemsMapping'] ?? [],
                $deliveryData['packageRef'] ?? null,
                $deliveryData['isDeleted'] ?? false,
                $deliveryData['draftId'] ?? null,
                $deliveryData['mainLocale'] ?? null,
                $deliveryData['supportedLocales'] ?? [],
                $deliveryData['translations'] ?? [],
                $deliveryData['isDisabled'] ?? false,
            );
            $delivery->clearUpdates();

            return $delivery;
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                sprintf('Cannot denormalize delivery: %s', $exception->getMessage()),
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
            /** @var Delivery $document */
            return new DocumentDriverData(
                $document->getId(),
                [
                    'tenantId' => $document->getTenantId(),
                    'createdAt' => $document->getCreatedAt()->getTimestamp(),
                    'configuration' => $document->getConfiguration(),
                    'compactTestFilePath' => $document->getQtiCompactTestFilePath(),
                    'qtiItemsMapping' => $document->getQtiItemsMapping(),
                    'packageRef' => $document->getPackageRef(),
                    'isDisabled' => $document->getIsDisabled(),
                    'isDeleted' => $document->isDeleted(),
                    'draftId' => $document->getDraftId(),
                    'mainLocale' => $document->getMainLocale(),
                    'supportedLocales' => $document->getSupportedLocales(),
                    'translations' => $document->getTranslations(),
                ],
            );
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                sprintf('Cannot normalize delivery: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    public function supports(DocumentDriverInterface $documentDriver, string $documentClass): bool
    {
        return is_a($documentClass, Delivery::class, true);
    }
}
