<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2026 (original work) Open Assessment Technologies SA;
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
    public const array COLUMNS = [
        self::COLUMN_DELIVERY_ID,
        self::COLUMN_TENANT_ID,
        self::COLUMN_STARTED_AT,
        self::COLUMN_EXTRA_STATE_DATA,
        self::COLUMN_RESULT_ID,
        self::COLUMN_LTI_LAUNCH_PARAMETERS,
        self::COLUMN_REVIEW_INLINE_COMMENT,
        self::COLUMN_QTI_COMPACT_TEST_FILE_PATH,
        self::COLUMN_QTI_SDK_ENCODED_TEST_SESSION,
        self::COLUMN_INITIALLY_SCORED_QTI_SDK_ENCODED_TEST_SESSION,
        self::COLUMN_LOCALE,
        self::COLUMN_STATUS,
        self::COLUMN_FINISHED_AT,
        self::COLUMN_CLOSE_AT,
        self::COLUMN_UPDATED_AT,
        self::COLUMN_IS_DELETED,
        self::COLUMN_INVALIDATION,
    ];
    public const string COLUMN_DELIVERY_ID = 'deliveryId';
    public const string COLUMN_TENANT_ID = 'tenantId';
    public const string COLUMN_STARTED_AT = 'startedAt';
    public const string COLUMN_EXTRA_STATE_DATA = 'extraStateData';
    public const string COLUMN_RESULT_ID = 'resultId';
    public const string COLUMN_LTI_LAUNCH_PARAMETERS = 'ltiLaunchParameters';
    public const string COLUMN_REVIEW_INLINE_COMMENT = 'reviewInlineComment';
    public const string COLUMN_QTI_COMPACT_TEST_FILE_PATH = 'qtiCompactTestFilePath';
    public const string COLUMN_QTI_SDK_ENCODED_TEST_SESSION = 'qtiSdkEncodedTestSession';
    public const string COLUMN_INITIALLY_SCORED_QTI_SDK_ENCODED_TEST_SESSION = 'initiallyScoredQtiSdkEncodedTestSession';
    public const string COLUMN_LOCALE = 'locale';
    public const string COLUMN_STATUS = 'status';
    public const string COLUMN_FINISHED_AT = 'finishedAt';
    public const string COLUMN_CLOSE_AT = 'closeAt';
    public const string COLUMN_UPDATED_AT = 'updatedAt';
    public const string COLUMN_IS_DELETED = 'isDeleted';
    public const string COLUMN_INVALIDATION = 'invalidation';

    /**
     * @throws DocumentNormalizerException
     */
    public function denormalizeDocument(DocumentDriverDataInterface $documentData, string $documentClass): DocumentInterface
    {
        try {
            $deliveryExecutionData = $documentData->getData();

            $invalidation = null;
            if (!empty($deliveryExecutionData[self::COLUMN_INVALIDATION])) {
                $invalidation = Invalidation::fromArray($deliveryExecutionData[self::COLUMN_INVALIDATION]);
            }

            $deliveryExecution = DeliveryExecutionFactory::create(
                $documentData->getId(),
                $deliveryExecutionData[self::COLUMN_LTI_LAUNCH_PARAMETERS],
                $deliveryExecutionData[self::COLUMN_QTI_SDK_ENCODED_TEST_SESSION],
                DeliveryExecutionExtraStateData::fromArray($deliveryExecutionData[self::COLUMN_EXTRA_STATE_DATA]),
                $deliveryExecutionData[self::COLUMN_STATUS],
                Date::createFromDefaultFormat($deliveryExecutionData[self::COLUMN_STARTED_AT]),
                Date::createFromDefaultFormat($deliveryExecutionData[self::COLUMN_FINISHED_AT]),
                Date::createFromDefaultFormat($deliveryExecutionData[self::COLUMN_CLOSE_AT]),
                Date::createFromDefaultFormat($deliveryExecutionData[self::COLUMN_UPDATED_AT] ?? null),
                empty($deliveryExecutionData[self::COLUMN_REVIEW_INLINE_COMMENT])
                    ? null
                    : new InlineFeedbackCollection($deliveryExecutionData[self::COLUMN_REVIEW_INLINE_COMMENT]),
                $deliveryExecutionData[self::COLUMN_IS_DELETED] ?? false,
                $deliveryExecutionData[self::COLUMN_LOCALE] ?? null,
                $invalidation,
                $deliveryExecutionData[self::COLUMN_INITIALLY_SCORED_QTI_SDK_ENCODED_TEST_SESSION] ?? null,
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
                    self::COLUMN_DELIVERY_ID => $document->getDeliveryId(),
                    self::COLUMN_TENANT_ID => $document->getTenantId(),
                    self::COLUMN_STARTED_AT => $document->getStartedAt()->format(Date::DEFAULT_FORMAT),
                    self::COLUMN_EXTRA_STATE_DATA => $this->normalizeExtraStateData($document),
                    self::COLUMN_RESULT_ID => $document->getResultId(),
                    self::COLUMN_LTI_LAUNCH_PARAMETERS => $document->getLtiLaunchParameters(),
                    self::COLUMN_REVIEW_INLINE_COMMENT => $document->getReviewInlineComment()?->toArray(),
                    self::COLUMN_QTI_COMPACT_TEST_FILE_PATH => $document->getQtiCompactTestFilePath(),
                    self::COLUMN_QTI_SDK_ENCODED_TEST_SESSION => $document->getQtiSdkEncodedTestSession(),
                    self::COLUMN_INITIALLY_SCORED_QTI_SDK_ENCODED_TEST_SESSION =>
                        $document->getInitiallyScoredQtiSdkEncodedTestSession(),
                    self::COLUMN_LOCALE => $document->getLocale(),
                    self::COLUMN_STATUS => $document->getStatus(),
                    self::COLUMN_FINISHED_AT => $document->getFinishedAt()?->format(Date::DEFAULT_FORMAT),
                    self::COLUMN_CLOSE_AT => $document->getCloseAt()?->format(Date::DEFAULT_FORMAT),
                    self::COLUMN_UPDATED_AT => $document->getUpdatedAt()?->format(Date::DEFAULT_FORMAT),
                    self::COLUMN_IS_DELETED => $document->isDeleted(),
                    self::COLUMN_INVALIDATION => $document->getinvalidation()?->toArray(),
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
