<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Delivery;

use App\Domain\Delivery\Model\Delivery;
use App\Repository\DeliveryRepository;
use Doctrine\ORM\Exception\ORMException;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;

class DeleteDeliveryService
{
    public function __construct(
        private readonly DeliveryRepository $deliveryRepository,
        private readonly FilesystemOperator $qtiPackageExtractorStorage,
        private readonly FilesystemOperator $qtiCompiledDeliveriesStorage,
    ) {
    }

    public function softDelete(Delivery $delivery): void
    {
        $delivery->setIsDeleted(true);
        $this->deliveryRepository->save($delivery);
    }

    /**
     * @throws ORMException
     * @throws FilesystemException
     */
    public function hardDelete(Delivery $delivery): void
    {
        $this->deliveryRepository->delete($delivery);

        if ($this->qtiPackageExtractorStorage->directoryExists($delivery->getId())) {
            $this->qtiPackageExtractorStorage->deleteDirectory($delivery->getId());
        }

        if ($this->qtiCompiledDeliveriesStorage->directoryExists($delivery->getId())) {
            $this->qtiCompiledDeliveriesStorage->deleteDirectory($delivery->getId());
        }
    }
}
