<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Domain\DeliveryExecution\Helper;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Domain\DeliveryExecution\Model\DeliveryExecutionKeyInfo;

final class DeliveryExecutionKeyHelper
{
    public static function createDeliveryExecutionKeyInfo(string $deliveryExecutionKey): ?DeliveryExecutionKeyInfo
    {
        $deliveryExecutionKeyParts = array_reverse(
            explode(
                DeliveryExecution::DOCUMENT_KEY_DELIMITER,
                $deliveryExecutionKey,
            ),
        );
        # Add default values for $tenantId, $attemptIdHash, $deliveryId, $userId, $mode
        $deliveryExecutionKeyParts += array_fill(0, 5, null);

        [$tenantId, $attemptIdHash, $deliveryId, $userId, $mode] = $deliveryExecutionKeyParts;

        if (empty($userId)) {
            return null;
        }

        return new DeliveryExecutionKeyInfo(
            $mode,
            $userId,
            $deliveryId,
            $attemptIdHash,
            $tenantId,
        );
    }
}
