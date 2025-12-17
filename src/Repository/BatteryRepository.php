<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Repository;

use App\DocumentManager\Filter\CollectionTenantIdFilterFactory;
use App\Domain\Battery\Model\Battery;
use OAT\Bundle\DocumentManagerBundle\Document\Collection\DocumentCollectionInterface;
use OAT\Bundle\DocumentManagerBundle\Manager\DocumentManagerInterface;
use OAT\Bundle\DocumentManagerBundle\Repository\DocumentRepository;

/**
 * @method Battery find(string $documentId)
 */
class BatteryRepository extends DocumentRepository
{
    public function __construct(
        DocumentManagerInterface $manager,
        private readonly CollectionTenantIdFilterFactory $collectionTenantIdFilterFactory,
    ) {
        parent::__construct($manager, Battery::class);
    }

    /**
     * @return DocumentCollectionInterface|Battery[]
     */
    public function findCollectionByTenantId(
        string $tenantId,
        ?int $limit = null,
        ?int $offset = null,
    ): DocumentCollectionInterface {
        return $this->findCollection(
            $this->collectionTenantIdFilterFactory->createForFindByTenantId(
                $this->manager->getHandlerForClass(Battery::class)->getConnection()->getDriver(),
                $tenantId,
                Battery::class,
            ),
            $limit,
            $offset,
        );
    }

    public function findCollectionByTenantIdAndIds(
        string $tenantId,
        array $ids,
        ?int $limit = null,
        ?int $offset = null,
    ): DocumentCollectionInterface {
        return $this->findCollection(
            $this->collectionTenantIdFilterFactory->createForFindByTenantIdAndIds(
                $this->manager->getHandlerForClass(Battery::class)->getConnection()->getDriver()::getName(),
                $tenantId,
                $ids,
            ),
            $limit,
            $offset,
        );
    }
}
