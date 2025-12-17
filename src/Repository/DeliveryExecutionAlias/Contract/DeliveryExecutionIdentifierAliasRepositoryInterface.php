<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Repository\DeliveryExecutionAlias\Contract;

use App\Domain\DeliveryExecution\Model\DeliveryExecutionAlias;

interface DeliveryExecutionIdentifierAliasRepositoryInterface
{
    public function findDeliveryExecutionId(string $tenantId, string $alias): ?string;

    public function saveDeliveryExecutionId(string $tenantId, string $alias, string $deliveryExecutionId): DeliveryExecutionAlias;

    public function deleteDeliveryExecutionId(string $aliasId): void;
}
