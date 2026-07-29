<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Battery\Exception\EmptyBatteryException;
use App\Domain\Battery\Generator\BatteryDistributionKeyGenerator;
use App\Domain\Battery\Model\Battery;
use App\Domain\Battery\Model\BatteryDistribution;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;
use OAT\Bundle\DocumentManagerBundle\Manager\DocumentManagerInterface;
use OAT\Bundle\DocumentManagerBundle\Repository\DocumentRepository;

/**
 * @method BatteryDistribution find(string $documentId)
 */
class BatteryDistributionRepository extends DocumentRepository
{
    public function __construct(
        DocumentManagerInterface $manager,
    ) {
        parent::__construct($manager, BatteryDistribution::class);
    }

    /**
     * @throws DocumentNotFoundException
     */
    public function findByBatteryAndUserId(string $batteryId, string $userId, ?string $attemptId): BatteryDistribution
    {
        // should be removed in a future, added just to keep backward compatibility with existing batteries
        try {
            return $this->find(BatteryDistributionKeyGenerator::generateBatteryDistributionKey($batteryId, $userId, $attemptId));
        } catch (DocumentNotFoundException) {
            return $this->find(BatteryDistributionKeyGenerator::generateBatteryDistributionKey($batteryId, $userId, null));
        }
    }

    /**
     * @throws EmptyBatteryException
     */
    public function findOrCreate(Battery $battery, string $userId, ?string $attemptId): BatteryDistribution
    {
        try {
            return $this->findByBatteryAndUserId($battery->getId(), $userId, $attemptId);
        } catch (DocumentNotFoundException) {
            return $this->createByBatteryAndUserId($battery, $userId, $attemptId);
        }
    }

    /**
     * @throws EmptyBatteryException
     */
    public function createByBatteryAndUserId(Battery $battery, string $userId, ?string $attemptId): BatteryDistribution
    {
        if (empty($battery->deliveries)) {
            throw new EmptyBatteryException('The battery does not contain any delivery');
        }

        $batteryDistribution = new BatteryDistribution(
            BatteryDistributionKeyGenerator::generateBatteryDistributionKey($battery->getId(), $userId, $attemptId),
            $userId,
            $battery,
        );
        $this->save($batteryDistribution);

        return $batteryDistribution;
    }
}
