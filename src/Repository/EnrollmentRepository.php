<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Repository;

use App\DocumentManager\Filter\CollectionEnrollmentFilterFactory;
use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\Enrollment\Model\Enrollment;
use OAT\Bundle\DocumentManagerBundle\Manager\DocumentManagerInterface;
use OAT\Bundle\DocumentManagerBundle\Repository\DocumentRepository;

/**
 * @method Enrollment find(string $documentId)
 */
class EnrollmentRepository extends DocumentRepository
{
    public function __construct(
        DocumentManagerInterface $manager,
        private readonly CollectionEnrollmentFilterFactory $collectionEnrollmentFilterFactory,
    ) {
        parent::__construct($manager, Enrollment::class);
    }

    public function findSession(DeliveryExecution $deliveryExecution): ?Enrollment
    {
        $enrollments = $this->findCollection(
            $this->collectionEnrollmentFilterFactory
                ->createForFindSessionByDeliveryExecutionId(
                    $this->manager->getHandlerForClass(Enrollment::class)->getConnection()->getDriver(),
                    $deliveryExecution->getId(),
                ),
            1,
            0,
        );

        return $enrollments->all()[0] ?? null;
    }
}
