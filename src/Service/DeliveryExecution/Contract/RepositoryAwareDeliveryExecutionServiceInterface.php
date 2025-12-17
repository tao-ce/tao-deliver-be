<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Service\DeliveryExecution\Contract;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use OAT\Bundle\DocumentManagerBundle\Exception\DocumentNotFoundException;

interface RepositoryAwareDeliveryExecutionServiceInterface
{
    //
    //  @start bunch of proxy method to the underlying repository
    //     According to clean architecture we shouldn't use repos directly without services
    //       it because in feature probably we will need add additional actions to it that style reduce amount of
    //       places with changes: events, validation etc..
    public function saveDeliveryExecution(DeliveryExecution $deliveryExecutionModel): void;
    public function deleteDeliveryExecution(DeliveryExecution $deliveryExecutionModel): void;

    /**
     * @throws DocumentNotFoundException
     */
    public function findDeliveryExecutionOrFail(string $deliveryExecutionId): DeliveryExecution;
    public function findDeliveryExecution(string $deliveryExecutionId): ?DeliveryExecution;
}
