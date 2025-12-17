<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ItemEnricher;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\TestRunner\ItemEnricher\Contract\ItemDataEnricherInterface;

class TrendItemEnricher implements ItemDataEnricherInterface
{
    /** Update @see self::TREND_ITEM_PREFIX_LENGTH if changed */
    private const TREND_ITEM_PREFIX = 'trendItem_';
    private const TREND_ITEM_PREFIX_LENGTH = 10;

    public function __construct(private readonly TrendItemPropertiesEnricher $trendItemPropertiesEnricher)
    {
    }

    public function enrich(DeliveryExecution $deliveryExecution, string $itemIdentifier, array $itemData): array
    {
        if (empty($itemData['data']['body']['elements'])) {
            return $itemData;
        }

        foreach ($itemData['data']['body']['elements'] as &$element) {
            if (str_starts_with($element['typeIdentifier'] ?? '', self::TREND_ITEM_PREFIX)) {
                $element['properties'] = $this->trendItemPropertiesEnricher->enrich(
                    $deliveryExecution,
                    substr($element['typeIdentifier'], self::TREND_ITEM_PREFIX_LENGTH),
                    $element['properties'],
                );
            }
        }
        unset($element);

        return $itemData;
    }
}
