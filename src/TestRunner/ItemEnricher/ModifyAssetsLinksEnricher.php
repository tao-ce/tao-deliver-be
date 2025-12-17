<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2022-2024 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ItemEnricher;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Generator\Asset\CloudCdnSignedUrlGenerator;
use App\TestRunner\ItemEnricher\Contract\ItemDataEnricherInterface;

readonly class ModifyAssetsLinksEnricher extends ItemDataUrlAbstractEnricher implements ItemDataEnricherInterface
{
    /**
     * return modified ItemData
     */
    public function enrich(DeliveryExecution $deliveryExecution, string $itemIdentifier, array $itemData): array
    {
        if (!empty($itemData['assets'])) {
            foreach ($itemData['assets'] as $type => $assets) {
                foreach ($assets as $assetKey => $assetValue) {
                    $path = $deliveryExecution->getAssetPath($assetValue, $itemIdentifier);
                    $itemData['assets'][$type][$assetKey] =
                        $this->signedUrlGeneratorRegistry->getGenerator(CloudCdnSignedUrlGenerator::NAME)
                            ->generateDownloadUrl($path);
                }
            }

            $this->auditDeliveryExecutionLogger->debug(
                sprintf(
                    '[%s][ModifyAssetsLinksEnricher] - modified assets links of item %s',
                    $deliveryExecution->getId(),
                    $itemIdentifier,
                ),
            );
        }

        return $itemData;
    }
}
