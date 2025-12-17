<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Normalizer;

use App\Domain\DeliveryExecution\Model\DeliveryExecutionAlias;
use Exception;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverData;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;
use OAT\Bundle\DocumentManagerBundle\Normalizer\AbstractDocumentNormalizer;

class DeliveryExecutionAliasNormalizer extends AbstractDocumentNormalizer
{
    /**
     * @throws DocumentNormalizerException
     */
    public function denormalizeDocument(DocumentDriverDataInterface $documentData, string $documentClass): DocumentInterface
    {
        try {
            $deliveryExecutionData = $documentData->getData();
            return new DeliveryExecutionAlias(
                $documentData->getId(),
                $deliveryExecutionData['deliveryExecutionId'],
            );
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                sprintf('Cannot denormalize delivery execution alias: %s', $exception->getMessage()),
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
            /** @var DeliveryExecutionAlias $document */
            return new DocumentDriverData(
                $document->getId(),
                [
                    'deliveryExecutionId' => $document->getDeliveryExecutionId(),
                ],
                $document->getUpdates(),
            );
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                sprintf('Cannot normalize delivery execution: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    public function supports(DocumentDriverInterface $documentDriver, string $documentClass): bool
    {
        return is_a($documentClass, DeliveryExecutionAlias::class, true);
    }
}
