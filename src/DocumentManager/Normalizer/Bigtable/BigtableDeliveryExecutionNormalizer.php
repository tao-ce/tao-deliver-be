<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Normalizer\Bigtable;

use App\DocumentManager\Normalizer\DeliveryExecutionNormalizer;
use App\Domain\DeliveryExecution\Model\Comment\InlineFeedbackCollection;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionExtraStateData;
use App\Domain\DeliveryExecution\Model\Invalidation;
use App\Helper\Date;
use App\Service\DeliveryExecution\DeliveryExecutionFactory;
use Exception;
use OAT\Bundle\BigtableDocumentManagerBundle\Driver\BigtableDocumentDriver;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverData;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;

class BigtableDeliveryExecutionNormalizer extends AbstractBigtableNormalizer
{
    private const COLUMN_ATTEMPT = 'attempt';
    private const COLUMN_ATTACHMENTS = 'attachments';
    private const COLUMN_ITEM_STATES = 'itemStates';
    private const COLUMN_TEMPORARY_ITEM_STATES = 'temporaryItemStates';
    private const COLUMN_REQUEST_IP = 'requestIp';
    private const EXTRACTED_EXTRA_DATA_COLUMNS = [
        self::COLUMN_ATTACHMENTS,
        self::COLUMN_ITEM_STATES,
        self::COLUMN_TEMPORARY_ITEM_STATES,
        self::COLUMN_REQUEST_IP,
    ];

    /**
     * @throws DocumentNormalizerException
     */
    public function denormalizeDocument(
        DocumentDriverDataInterface $documentData,
        string $documentClass,
    ): DocumentInterface {
        try {
            $deliveryExecutionData = $documentData->getData()[self::DATA_COLUMN_FAMILY];
            $extraStateData = unserialize(gzuncompress($deliveryExecutionData['extraStateData'][0]['value']));
            foreach (self::EXTRACTED_EXTRA_DATA_COLUMNS as $extraDataColumn) {
                if (empty($deliveryExecutionData[$extraDataColumn][0]['value'])) {
                    continue;
                }
                $extraStateData[$extraDataColumn] = igbinary_unserialize(
                    $deliveryExecutionData[$extraDataColumn][0]['value'],
                );
            }
            // TODO include the attempt column under @see self::EXTRACTED_EXTRA_DATA_COLUMNS
            //  to unset its duplicate from the extraStateData.
            //  Keeping it here for backward compatibility in case the Deliver BE version gets downgraded.
            if (isset($deliveryExecutionData[self::COLUMN_ATTEMPT][0]['value'])) {
                $extraStateData[self::COLUMN_ATTEMPT] = (int)$deliveryExecutionData[self::COLUMN_ATTEMPT][0]['value'];
            }

            $reviewInlineComment = null;
            if (!empty($deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_REVIEW_INLINE_COMMENT][0]['value'])) {
                $reviewInlineComment = igbinary_unserialize(
                    $deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_REVIEW_INLINE_COMMENT][0]['value'],
                );
            }

            $invalidation = null;
            if (!empty($deliveryExecutionData['invalidation'][0]['value'])) {
                $invalidationData = igbinary_unserialize($deliveryExecutionData['invalidation'][0]['value']);
                if ($invalidationData !== null) {
                    $invalidation = Invalidation::fromArray($invalidationData);
                }
            }

            $deliveryExecution = DeliveryExecutionFactory::create(
                $documentData->getId(),
                unserialize(gzuncompress($deliveryExecutionData['ltiLaunchParameters'][0]['value'])),
                $deliveryExecutionData['qtiSdkEncodedTestSession'][0]['value'] === ''
                    ? null
                    : gzuncompress($deliveryExecutionData['qtiSdkEncodedTestSession'][0]['value']),
                DeliveryExecutionExtraStateData::fromArray($extraStateData),
                $deliveryExecutionData['status'][0]['value'],
                Date::createFromDefaultFormat($deliveryExecutionData['startedAt'][0]['value']),
                Date::createFromDefaultFormat($deliveryExecutionData['finishedAt'][0]['value']),
                Date::createFromDefaultFormat($deliveryExecutionData['closeAt'][0]['value']),
                Date::createFromDefaultFormat($deliveryExecutionData['updatedAt'][0]['value'] ?? null),
                $reviewInlineComment ? new InlineFeedbackCollection($reviewInlineComment) : null,
                (bool)($deliveryExecutionData['isDeleted'][0]['value'] ?? false),
                isset($deliveryExecutionData['locale'][0]['value']) && $deliveryExecutionData['locale'][0]['value'] !== ''
                    ? $deliveryExecutionData['locale'][0]['value']
                    : null,
                $invalidation,
                empty($deliveryExecutionData['initiallyScoredQtiSdkEncodedTestSession'][0]['value'])
                    ? null
                    : gzuncompress($deliveryExecutionData['initiallyScoredQtiSdkEncodedTestSession'][0]['value']),
            );

            $deliveryExecution->clearUpdates();

            return $deliveryExecution;
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                sprintf(
                    'Cannot denormalize delivery execution with id: "%s" with errorMessage: %s',
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
            /** @var DeliveryExecution $document */
            $extraStateData = $this->normalizeExtraStateData($document);
            $reviewInlineComment = $this->normalizeReviewInlineComment($document) ?? [];
            $attempt = $extraStateData[self::COLUMN_ATTEMPT];
            $attachments = $extraStateData[self::COLUMN_ATTACHMENTS];
            $itemStates = $extraStateData[self::COLUMN_ITEM_STATES];
            $temporaryItemStates = $extraStateData[self::COLUMN_TEMPORARY_ITEM_STATES];
            $requestIp = $extraStateData[self::COLUMN_REQUEST_IP];
            $extraStateData = array_diff_key($extraStateData, array_flip(self::EXTRACTED_EXTRA_DATA_COLUMNS));
            return new DocumentDriverData(
                $document->getId(),
                [
                    self::DATA_COLUMN_FAMILY => [
                        'startedAt' => $document->getStartedAt()->format(Date::DEFAULT_FORMAT),
                        'extraStateData' => gzcompress(serialize($extraStateData)),
                        self::COLUMN_ATTEMPT => (int)$attempt,
                        self::COLUMN_ATTACHMENTS => igbinary_serialize($attachments),
                        self::COLUMN_ITEM_STATES => igbinary_serialize($itemStates),
                        self::COLUMN_REQUEST_IP => $requestIp ? igbinary_serialize($requestIp) : '',
                        self::COLUMN_TEMPORARY_ITEM_STATES => igbinary_serialize($temporaryItemStates),
                        DeliveryExecutionNormalizer::COLUMN_REVIEW_INLINE_COMMENT => igbinary_serialize($reviewInlineComment),
                        'ltiLaunchParameters' => gzcompress(serialize($document->getLtiLaunchParameters())),
                        'qtiSdkEncodedTestSession' => gzcompress($document->getQtiSdkEncodedTestSession() ?? ''),
                        'initiallyScoredQtiSdkEncodedTestSession' =>
                            $document->getInitiallyScoredQtiSdkEncodedTestSession()
                                ? gzcompress($document->getInitiallyScoredQtiSdkEncodedTestSession())
                                : null,
                        'locale' => $document->getLocale() ?? '',
                        'status' => $document->getStatus(),
                        'finishedAt' => $document->getFinishedAt()
                            ? $document->getFinishedAt()->format(Date::DEFAULT_FORMAT)
                            : '',
                        'closeAt' => $document->getCloseAt()
                            ? $document->getCloseAt()->format(Date::DEFAULT_FORMAT)
                            : '',
                        'updatedAt' => $document->getUpdatedAt()
                            ? $document->getUpdatedAt()->format(Date::DEFAULT_FORMAT)
                            : '',
                        'isDeleted' => $document->isDeleted(),
                        'invalidation' => igbinary_serialize($document->getinvalidation()?->toArray()),
                    ],
                ],
                $document->getUpdates(),
            );
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                sprintf(
                    'Cannot normalize delivery execution with id: "%s" with errorMessage: %s',
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
        return is_a($documentClass, DeliveryExecution::class, true)
            && $documentDriver instanceof BigtableDocumentDriver;
    }

    private function normalizeExtraStateData(DeliveryExecution $deliveryExecution): array
    {
        return $deliveryExecution->getExtraStateData()->toArray();
    }

    private function normalizeReviewInlineComment(DeliveryExecution $deliveryExecution): array
    {
        return $deliveryExecution->getReviewInlineComment()?->toArray() ?? [];
    }
}
