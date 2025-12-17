<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Delivery;

use App\Domain\Delivery\Model\Delivery;
use App\Generator\UuidGenerator;
use App\Repository\DeliveryRepository;
use Carbon\Carbon;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\FilesystemReader;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

class CloneDeliveryService
{
    /** @var FilesystemOperator[] */
    private readonly array $dataStorages;

    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly UuidGenerator $idGenerator,
        FilesystemOperator $qtiAssetManagerStorage,
        FilesystemOperator $qtiCompiledDeliveriesStorage,
        private readonly DeliveryRepository $repository,
    ) {
        $this->dataStorages = [$qtiAssetManagerStorage, $qtiCompiledDeliveriesStorage];
    }

    public function clone(Delivery $delivery): Delivery
    {
        if ($delivery->getDraftId() !== null) {
            return $delivery;
        }

        $lock = $this->acquireLock($delivery);

        try {
            do {
                $id = $this->idGenerator->generateMedium();
                $this->repository->findUnrestricted($id);
            } while (true);
        } catch (DocumentNotFoundException) {
        }

        $this->copyData($delivery->getId(), $id);

        $delivery->setDraftId($id);
        $configuration = $delivery->getConfiguration();
        $configuration['label'] = @trim("{$configuration['label']} (cloned from {$delivery->getId()})");
        $clonedDelivery = new Delivery(
            $id,
            $delivery->getTenantId(),
            Carbon::now(),
            str_replace($delivery->getId(), $id, $delivery->getQtiCompactTestFilePath()),
            $configuration,
            $delivery->getQtiItemsMapping(),
            isDeleted: true,
        );
        $this->repository->save($clonedDelivery);
        $this->repository->save($delivery);

        $lock->release();

        return $delivery;
    }

    private function acquireLock(Delivery $delivery): LockInterface
    {
        $lock = $this->lockFactory->createLock($delivery->getId());
        $lock->acquire();

        return $lock;
    }

    public function copyData(string $oldId, string $newId): void
    {
        foreach ($this->dataStorages as $dataStorage) {
            foreach (
                $dataStorage->listContents(
                    $oldId,
                    FilesystemReader::LIST_DEEP,
                ) as $node
            ) {
                $node->isDir()
                    ? $dataStorage->createDirectory($node->path())
                    : $dataStorage->copy($node->path(), str_replace($oldId, $newId, $node->path()));
            }
        }
    }
}
