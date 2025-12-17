<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Repository;

use App\DocumentManager\Filter\DeliveryExecution\DeliveryExecutionCollectionFilterFactory;
use App\Domain\DeliveryExecution\Helper\DeliveryExecutionKeyHelper;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Lti\LtiCustomSettings;
use Carbon\Carbon;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Bundle\DocumentManagerBundle\Manager\DocumentManagerInterface;
use OAT\Bundle\DocumentManagerBundle\Repository\DocumentRepository;

class DeliveryExecutionRepository extends DocumentRepository
{
    private DeliveryExecution $lastLoadDeliveryExecution;

    public function __construct(
        DocumentManagerInterface $manager,
        private readonly DeliveryExecutionCollectionFilterFactory $filterFactory,
        private LtiCustomSettings $ltiCustomSettings,
    ) {
        parent::__construct($manager, DeliveryExecution::class);
    }

    public function find(string $documentId): DeliveryExecution
    {
        if (empty($this->lastLoadDeliveryExecution) || $this->lastLoadDeliveryExecution->getId() !== $documentId) {
            $keyInfo = DeliveryExecutionKeyHelper::createDeliveryExecutionKeyInfo($documentId);
            if (!$keyInfo?->isReview()) {
                $this->lastLoadDeliveryExecution = parent::find($documentId);
            } else {
                try {
                    $originalDeliveryExecution = $this->find((string)$keyInfo);
                    $extraState = $originalDeliveryExecution->getExtraStateData();
                    $qtiSession = $originalDeliveryExecution->getQtiSdkEncodedTestSession();
                } catch (DocumentNotFoundException $exception) {
                    if ($keyInfo->getUserId()) {
                        throw $exception;
                    }
                    $originalDeliveryExecution = null;
                    $extraState = null;
                    $qtiSession = '';
                }
                $parameters = [
                    'result_id' => DeliveryExecution::DRY_RUN_ATTEMPT_ID,
                    'user_id' => $keyInfo->getUserId(),
                    'user_name' => $keyInfo->getUserId(),
                ];
                if ($this->ltiCustomSettings->isAutoReviewModeEnabled()) {
                    $parameters['launch_presentation_return_url'] =
                        $originalDeliveryExecution->getLtiLaunchParameters()['launch_presentation_return_url'] ?? null;
                }

                $this->lastLoadDeliveryExecution = new DeliveryExecution(
                    $documentId,
                    $keyInfo->getDeliveryId(),
                    $keyInfo->getTenantId(),
                    Carbon::now(),
                    $parameters,
                    $qtiSession,
                    $extraState,
                    reviewInlineComment: $originalDeliveryExecution?->getReviewInlineComment(),
                    originalDeliveryExecution: $originalDeliveryExecution,
                    locale: $originalDeliveryExecution?->getLocale(),
                );
            }
        }

        return $this->lastLoadDeliveryExecution;
    }

    /**
     * @return DeliveryExecution[]
     */
    public function findByDeliveryId(string $deliveryId): iterable
    {
        $filter = $this->filterFactory->createForFindByDeliveryId(
            $this->manager->getHandlerForClass(DeliveryExecution::class)->getConnection()->getDriver(),
            $deliveryId,
        );

        $documentsDriverData = $this->getDriver()->getDocumentsCollectionData(
            $this->getDocumentStorageName(),
            $filter->getFilter(),
        );

        // BigTable closes the stream if the connection stays open for too long;
        // raw data must be fetched immediately
        foreach (iterator_to_array($documentsDriverData) as $documentDriverData) {
            yield $this->getNormalizer()->denormalizeDocument($documentDriverData, $this->documentClass);
        }
    }
}
