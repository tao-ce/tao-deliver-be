<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DocumentManager\Normalizer;

use App\Domain\Enrollment\Model\Enrollment;
use Exception;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverData;
use OAT\Bundle\DocumentManagerBundle\Driver\Data\DocumentDriverDataInterface;
use OAT\Bundle\DocumentManagerBundle\Driver\DocumentDriverInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNormalizerException;
use OAT\Bundle\DocumentManagerBundle\Normalizer\AbstractDocumentNormalizer;

class EnrollmentNormalizer extends AbstractDocumentNormalizer
{
    /**
     * @throws DocumentNormalizerException
     * @return DocumentInterface
     *
     */
    public function denormalizeDocument(
        DocumentDriverDataInterface $documentData,
        string $documentClass,
    ): DocumentInterface {
        try {
            $data = $documentData->getData();

            $enrollment = new Enrollment(
                id: $documentData->getId(),
                campaignId: $data['campaignId'] ?? '',
                campaignName: $data['campaignName'] ?? '',
                sessionId: $data['sessionId'] ?? '',
                sessionName: $data['sessionName'] ?? '',
                sessionTemplateId: $data['sessionTemplateId'] ?? '',
                sessionTemplateName: $data['sessionTemplateName'] ?? '',
                testCategory: $data['testCategory'] ?? null,
            );
            $enrollment->clearUpdates();

            return $enrollment;
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                message: sprintf(
                    'Cannot denormalize enrollment:%s Error: %s',
                    $documentData->getId(),
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }
    }

    /**
     * @param Enrollment $document
     *
     * @throws DocumentNormalizerException
     *
     * @return DocumentDriverDataInterface
     */
    public function normalizeDocument(DocumentInterface $document): DocumentDriverDataInterface
    {
        try {
            $normalizedEnrollment = json_decode(json_encode($document), true);
            unset($normalizedEnrollment['id']);

            return new DocumentDriverData($document->getId(), $normalizedEnrollment);
        } catch (Exception $exception) {
            throw new DocumentNormalizerException(
                sprintf('Cannot normalize enrollment: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    public function supports(DocumentDriverInterface $documentDriver, string $documentClass): bool
    {
        return is_a($documentClass, Enrollment::class, true);
    }
}
