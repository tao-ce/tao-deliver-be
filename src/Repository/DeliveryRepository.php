<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019-2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Repository;

use App\DocumentManager\Filter\CollectionTenantIdFilterFactory;
use App\Domain\Delivery\Model\Delivery;
use OAT\Bundle\DocumentManagerBundle\Document\Collection\DocumentCollectionInterface;
use OAT\Bundle\DocumentManagerBundle\Document\DocumentInterface;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Bundle\DocumentManagerBundle\Filter\DocumentCollectionFilterInterface;
use OAT\Bundle\DocumentManagerBundle\Manager\DocumentManagerInterface;
use OAT\Bundle\DocumentManagerBundle\Repository\DocumentRepository;

class DeliveryRepository extends DocumentRepository
{
    private ?Delivery $currentDelivery = null;

    public function __construct(
        DocumentManagerInterface $manager,
        private readonly CollectionTenantIdFilterFactory $collectionTenantIdFilterFactory,
    ) {
        parent::__construct($manager, Delivery::class);
    }

    /**
     * @return DocumentCollectionInterface|Delivery[]
     */
    public function findCollectionByTenantId(
        string $tenantId,
        ?int $limit = null,
        ?int $offset = null,
    ): DocumentCollectionInterface {
        return $this->findCollection(
            $this->collectionTenantIdFilterFactory->createForFindByTenantId(
                $this->manager->getHandlerForClass(Delivery::class)->getConnection()->getDriver(),
                $tenantId,
                Delivery::class,
            ),
            $limit,
            $offset,
        );
    }

    /**
     * @throws DocumentNotFoundException
     */
    public function find(string $documentId): Delivery
    {
        $delivery = $this->findUnrestricted($documentId);
        if ($delivery->isDeleted()) {
            throw new DocumentNotFoundException(sprintf(
                "Document class '%s' with id '%s' not found",
                Delivery::class,
                $documentId,
            ));
        }

        return $delivery;
    }

    public function save(DocumentInterface $document): void
    {
        parent::save($document);
        if ($this->currentDelivery) {
            /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
            $this->currentDelivery = $document;
        }
    }

    public function delete(DocumentInterface $document): void
    {
        parent::delete($document);
        $this->currentDelivery = null;
    }

    public function saveCollection(DocumentCollectionInterface $documentCollection): void
    {
        parent::saveCollection($documentCollection);
        if (!$this->currentDelivery || !$documentCollection->has($this->currentDelivery->getId())) {
            return;
        }
        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->currentDelivery = $documentCollection->get($this->currentDelivery->getId());
    }

    public function deleteCollection(DocumentCollectionInterface $documentCollection): void
    {
        parent::deleteCollection($documentCollection);
        $this->currentDelivery = null;
    }

    /**
     * @throws DocumentNotFoundException
     */
    public function findUnrestricted(string $documentId): Delivery
    {
        if (!$this->currentDelivery || $this->currentDelivery->getId() !== $documentId) {
            /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
            $this->currentDelivery = parent::find($documentId);
        }

        return $this->currentDelivery;
    }

    /**
     * @param DocumentCollectionFilterInterface|null $filter
     * @param int|null $limit
     * @param int|null $offset
     * @return DocumentCollectionInterface|Delivery[]
     */
    public function findCollection(?DocumentCollectionFilterInterface $filter = null, ?int $limit = null, ?int $offset = null): DocumentCollectionInterface
    {
        /** @var DocumentCollectionInterface|Delivery[] $collection */
        $collection = parent::findCollection($filter, $limit, $offset);

        foreach ($collection as $document) {
            if ($document->isDeleted()) {
                $collection->remove($document);
            }
        }

        return $collection;
    }
}
