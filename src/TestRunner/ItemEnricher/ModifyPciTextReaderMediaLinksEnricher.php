<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\TestRunner\ItemEnricher;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use App\Generator\Asset\CloudCdnSignedUrlGenerator;
use App\TestRunner\ItemEnricher\Contract\ItemDataEnricherInterface;

readonly class ModifyPciTextReaderMediaLinksEnricher extends ItemDataUrlAbstractEnricher implements ItemDataEnricherInterface
{
    /**
     * return modified ItemData
     */
    public function enrich(DeliveryExecution $deliveryExecution, string $itemIdentifier, array $itemData): array
    {
        if (!empty($itemData['data']['body']['elements'])) {
            $isChanged = false;
            foreach ($itemData['data']['body']['elements'] as &$element) {
                if (empty($element['properties'])) {
                    continue;
                }
                foreach ($element['properties'] as &$assetValue) {
                    if (!is_string($assetValue)) {
                        continue;
                    }

                    $assetValue = preg_replace_callback(
                        '/\bbase64_decoded_[0-9a-f]+(?:\.\w+)?\b/',
                        fn(array $matches) => $this->signedUrlGeneratorRegistry->getGenerator(
                            CloudCdnSignedUrlGenerator::NAME,
                        )->generateDownloadUrl($deliveryExecution->getAssetPath($matches[0], $itemIdentifier)),
                        $assetValue,
                    );

                    $isChanged = true;
                }
            }

            if ($isChanged) {
                $this->auditDeliveryExecutionLogger->debug(
                    sprintf(
                        '[%s][ModifyPciTextReaderMediaLinksEnricher] - modified assets links of item %s',
                        $deliveryExecution->getId(),
                        $itemIdentifier,
                    ),
                );
            }
        }

        return $itemData;
    }
}
