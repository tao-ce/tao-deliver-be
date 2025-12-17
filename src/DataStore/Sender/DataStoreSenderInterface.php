<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2021-2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\DataStore\Sender;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;

interface DataStoreSenderInterface
{
    /**
     * Send DeliveryExecution data to the configured datastore
     * @param DeliveryExecution $deliveryExecution
     */
    public function send(DeliveryExecution $deliveryExecution): void;
}
