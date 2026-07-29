<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2026 (original work) Open Assessment Technologies SA;
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
    private const array COLUMNS = [
        ...self::EXTRACTED_EXTRA_DATA_COLUMNS,
        ...DeliveryExecutionNormalizer::COLUMNS,
    ];
    private const string COLUMN_ATTEMPT = 'attempt';
    private const string COLUMN_ATTACHMENTS = 'attachments';
    private const string COLUMN_ITEM_STATES = 'itemStates';
    private const string COLUMN_TEMPORARY_ITEM_STATES = 'temporaryItemStates';
    private const string COLUMN_REQUEST_IP = 'requestIp';
    private const array EXTRACTED_EXTRA_DATA_COLUMNS = [
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
            $extraStateData = unserialize(
                gzuncompress($deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_EXTRA_STATE_DATA][0]['value']),
            );
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
            if (!empty($deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_INVALIDATION][0]['value'])) {
                $invalidationData = igbinary_unserialize(
                    $deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_INVALIDATION][0]['value'],
                );
                if ($invalidationData !== null) {
                    $invalidation = Invalidation::fromArray($invalidationData);
                }
            }

            $deliveryExecution = DeliveryExecutionFactory::create(
                $documentData->getId(),
                unserialize(
                    gzuncompress(
                        $deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_LTI_LAUNCH_PARAMETERS][0]['value'],
                    ),
                ),
                $deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_QTI_SDK_ENCODED_TEST_SESSION][0]['value'] === ''
                    ? null
                    : gzuncompress(
                        $deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_QTI_SDK_ENCODED_TEST_SESSION][0]['value'],
                    ),
                DeliveryExecutionExtraStateData::fromArray($extraStateData),
                $deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_STATUS][0]['value'],
                Date::createFromDefaultFormat(
                    $deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_STARTED_AT][0]['value'],
                ),
                Date::createFromDefaultFormat(
                    $deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_FINISHED_AT][0]['value'],
                ),
                Date::createFromDefaultFormat(
                    $deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_CLOSE_AT][0]['value'],
                ),
                Date::createFromDefaultFormat(
                    $deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_UPDATED_AT][0]['value'] ?? null,
                ),
                $reviewInlineComment ? new InlineFeedbackCollection($reviewInlineComment) : null,
                (bool)($deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_IS_DELETED][0]['value'] ?? false),
                isset($deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_LOCALE][0]['value'])
                && $deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_LOCALE][0]['value'] !== ''
                    ? $deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_LOCALE][0]['value']
                    : null,
                $invalidation,
                empty($deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_INITIALLY_SCORED_QTI_SDK_ENCODED_TEST_SESSION][0]['value'])
                    ? null
                    : gzuncompress(
                        $deliveryExecutionData[DeliveryExecutionNormalizer::COLUMN_INITIALLY_SCORED_QTI_SDK_ENCODED_TEST_SESSION][0]['value'],
                    ),
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
            $updatedColumns = $document->getUpdates();
            if (!array_diff($updatedColumns, self::COLUMNS)) {
                $updatedColumns = array_values(
                    array_diff($updatedColumns, [DeliveryExecutionNormalizer::COLUMN_EXTRA_STATE_DATA]),
                );
            }
            return new DocumentDriverData(
                $document->getId(),
                [
                    self::DATA_COLUMN_FAMILY => [
                        DeliveryExecutionNormalizer::COLUMN_STARTED_AT => $document->getStartedAt()->format(Date::DEFAULT_FORMAT),
                        DeliveryExecutionNormalizer::COLUMN_EXTRA_STATE_DATA => gzcompress(serialize($extraStateData)),
                        self::COLUMN_ATTEMPT => (int)$attempt,
                        self::COLUMN_ATTACHMENTS => igbinary_serialize($attachments),
                        self::COLUMN_ITEM_STATES => igbinary_serialize($itemStates),
                        self::COLUMN_REQUEST_IP => $requestIp ? igbinary_serialize($requestIp) : '',
                        self::COLUMN_TEMPORARY_ITEM_STATES => igbinary_serialize($temporaryItemStates),
                        DeliveryExecutionNormalizer::COLUMN_REVIEW_INLINE_COMMENT => igbinary_serialize($reviewInlineComment),
                        DeliveryExecutionNormalizer::COLUMN_LTI_LAUNCH_PARAMETERS => gzcompress(serialize($document->getLtiLaunchParameters())),
                        DeliveryExecutionNormalizer::COLUMN_QTI_SDK_ENCODED_TEST_SESSION => gzcompress($document->getQtiSdkEncodedTestSession() ?? ''),
                        DeliveryExecutionNormalizer::COLUMN_INITIALLY_SCORED_QTI_SDK_ENCODED_TEST_SESSION =>
                            $document->getInitiallyScoredQtiSdkEncodedTestSession()
                                ? gzcompress($document->getInitiallyScoredQtiSdkEncodedTestSession())
                                : null,
                        DeliveryExecutionNormalizer::COLUMN_LOCALE => $document->getLocale() ?? '',
                        DeliveryExecutionNormalizer::COLUMN_STATUS => $document->getStatus(),
                        DeliveryExecutionNormalizer::COLUMN_FINISHED_AT => $document->getFinishedAt()
                            ? $document->getFinishedAt()->format(Date::DEFAULT_FORMAT)
                            : '',
                        DeliveryExecutionNormalizer::COLUMN_CLOSE_AT => $document->getCloseAt()
                            ? $document->getCloseAt()->format(Date::DEFAULT_FORMAT)
                            : '',
                        DeliveryExecutionNormalizer::COLUMN_UPDATED_AT => $document->getUpdatedAt()
                            ? $document->getUpdatedAt()->format(Date::DEFAULT_FORMAT)
                            : '',
                        DeliveryExecutionNormalizer::COLUMN_IS_DELETED => $document->isDeleted(),
                        DeliveryExecutionNormalizer::COLUMN_INVALIDATION => igbinary_serialize($document->getinvalidation()?->toArray()),
                    ],
                ],
                $updatedColumns,
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
