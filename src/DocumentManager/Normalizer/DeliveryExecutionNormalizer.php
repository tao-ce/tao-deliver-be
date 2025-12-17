<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Normalizer;

use App\Domain\DeliveryExecution\Model\Comment\InlineFeedbackCollection;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Domain\DeliveryExecution\Model\Invalidation;
use App\Helper\Date;
use App\Service\DeliveryExecution\DeliveryExecutionFactory;
use Exception;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverData;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;
use OAT\Bundle\DocumentManagerBundle\Normalizer\AbstractDocumentNormalizer;

class DeliveryExecutionNormalizer extends AbstractDocumentNormalizer
{
    public const COLUMN_REVIEW_INLINE_COMMENT = 'reviewInlineComment';
    /**
     * @throws DocumentNormalizerException
     */
    public function denormalizeDocument(DocumentDriverDataInterface $documentData, string $documentClass): DocumentInterface
    {
        try {
            $deliveryExecutionData = $documentData->getData();

            $invalidation = null;
            if (!empty($deliveryExecutionData['invalidation'])) {
                $invalidation = Invalidation::fromArray($deliveryExecutionData['invalidation']);
            }

            $deliveryExecution = DeliveryExecutionFactory::create(
                $documentData->getId(),
                $deliveryExecutionData['ltiLaunchParameters'],
                $deliveryExecutionData['qtiSdkEncodedTestSession'],
                DeliveryExecutionExtraStateData::fromArray($deliveryExecutionData['extraStateData']),
                $deliveryExecutionData['status'],
                Date::createFromDefaultFormat($deliveryExecutionData['startedAt']),
                Date::createFromDefaultFormat($deliveryExecutionData['finishedAt']),
                Date::createFromDefaultFormat($deliveryExecutionData['closeAt']),
                Date::createFromDefaultFormat($deliveryExecutionData['updatedAt'] ?? null),
                empty($deliveryExecutionData[self::COLUMN_REVIEW_INLINE_COMMENT])
                    ? null
                    : new InlineFeedbackCollection($deliveryExecutionData[self::COLUMN_REVIEW_INLINE_COMMENT]),
                $deliveryExecutionData['isDeleted'] ?? false,
                $deliveryExecutionData['locale'] ?? null,
                $invalidation,
                $deliveryExecutionData['initiallyScoredQtiSdkEncodedTestSession'] ?? null,
            );

            $deliveryExecution->clearUpdates();

            return $deliveryExecution;
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                sprintf('Cannot denormalize delivery execution: %s', $exception->getMessage()),
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
            /** @var DeliveryExecution $document */
            return new DocumentDriverData(
                $document->getId(),
                [
                    'deliveryId' => $document->getDeliveryId(),
                    'tenantId' => $document->getTenantId(),
                    'startedAt' => $document->getStartedAt()->format(Date::DEFAULT_FORMAT),
                    'extraStateData' => $this->normalizeExtraStateData($document),
                    'resultId' => $document->getResultId(),
                    'ltiLaunchParameters' => $document->getLtiLaunchParameters(),
                    self::COLUMN_REVIEW_INLINE_COMMENT => $document->getReviewInlineComment()?->toArray(),
                    'qtiCompactTestFilePath' => $document->getQtiCompactTestFilePath(),
                    'qtiSdkEncodedTestSession' => $document->getQtiSdkEncodedTestSession(),
                    'initiallyScoredQtiSdkEncodedTestSession' =>
                        $document->getInitiallyScoredQtiSdkEncodedTestSession(),
                    'locale' => $document->getLocale(),
                    'status' => $document->getStatus(),
                    'finishedAt' => $document->getFinishedAt()?->format(Date::DEFAULT_FORMAT),
                    'closeAt' => $document->getCloseAt()?->format(Date::DEFAULT_FORMAT),
                    'updatedAt' => $document->getUpdatedAt()?->format(Date::DEFAULT_FORMAT),
                    'isDeleted' => $document->isDeleted(),
                    'invalidation' => $document->getinvalidation()?->toArray(),
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
        return is_a($documentClass, DeliveryExecution::class, true);
    }

    private function normalizeExtraStateData(DeliveryExecution $deliveryExecution): array
    {
        return $deliveryExecution->getExtraStateData()->toArray();
    }
}
