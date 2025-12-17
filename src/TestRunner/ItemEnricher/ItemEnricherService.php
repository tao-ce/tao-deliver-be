<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ItemEnricher;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\TestRunner\ItemEnricher\Contract\ItemDataEnricherInterface;
use App\TestRunner\ItemEnricher\Contract\ItemEnricherInterface;
use App\TestRunner\ItemEnricher\Contract\ItemStateEnricherInterface;

class ItemEnricherService implements ItemEnricherInterface
{
    /**
     * @param ItemStateEnricherInterface[] $enricherStateList
     * @param ItemDataEnricherInterface[] $enricherDataList
     */
    public function __construct(private iterable $enricherStateList, private iterable $enricherDataList)
    {
    }

    public function enrichState(mixed $responseVariable): mixed
    {
        foreach ($this->enricherStateList as $enricher) {
            $responseVariable = $enricher->enrich($responseVariable);
        }
        return $responseVariable;
    }

    public function enrichData(DeliveryExecution $deliveryExecution, string $itemIdentifier, array $itemData): array
    {
        foreach ($this->enricherDataList as $enricher) {
            $itemData = $enricher->enrich($deliveryExecution, $itemIdentifier, $itemData);
        }
        return $itemData;
    }
}
