<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2019 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\Delivery;

use App\Domain\Delivery\Model\Delivery;
use App\Repository\DeliveryRepository;
use Exception;
use JsonException;
use Psr\Log\LoggerInterface;

class UpdateDeliveryService
{
    public function __construct(
        private readonly DeliveryRepository $repository,
        private readonly LoggerInterface $auditPlatformLogger,
    ) {
    }


    /**
     * @throws JsonException
     */
    public function update(Delivery $delivery, array $configuration): Delivery
    {
        $delivery = $delivery->setConfiguration($configuration);

        $this->repository->save($delivery);
        $this->auditPlatformLogger->info(
            sprintf(
                '[%s] Delivery was updated with configuration: %s',
                $delivery->getId(),
                json_encode($configuration, JSON_THROW_ON_ERROR),
            ),
        );

        return $delivery;
    }
}
