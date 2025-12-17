<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2025 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\FlatTestMap\Service;

use App\FlatTestMap\Input\FlatMapSearchInput;
use App\FlatTestMap\Output\FlatMap;
use App\FlatTestMap\Output\Item;
use App\TestRunner\Service\GetItemDataService;
use OAT\Bundle\QtiBundle\Accessor\TestSessionAccessor;
use OAT\Bundle\QtiBundle\Factory\TestSessionAccessorFactory;
use qtism\data\AssessmentItemRef;
use qtism\data\IAssessmentItem;
use qtism\runtime\tests\RouteItem;

final readonly class FlatMapService
{
    public function __construct(
        private TestSessionAccessorFactory $testSessionAccessorFactory,
        private GetItemDataService $itemDataService,
    ) {
    }

    public function createFlatMap(FlatMapSearchInput $input): FlatMap
    {
        $testMap = new FlatMap();
        $testSession = $this->createSessionAccessor($input)->instantiate();
        /** @var RouteItem $routeItem */
        foreach ($testSession->getRoute()->getAllRouteItems() as $routeItem) {
            /** @var IAssessmentItem&AssessmentItemRef $item */
            $item = $routeItem->getAssessmentItemRef();
            $itemData = $this->itemDataService->getItemDataByDelivery(
                $item->getIdentifier(),
                $input->delivery,
                $input->locale,
            );
            if (empty($itemData['data']['body']['elements'])) {
                continue;
            }
            $responseIds = [];
            foreach ($itemData['data']['body']['elements'] as $element) {
                if (!in_array($element['qtiClass'], $input->includeOnlyInteraction)) {
                    if ($element['qtiClass'] === 'include') {
                        continue;
                    }
                    continue 2;
                }

                if (
                    !empty($element['attributes']['responseIdentifier'])
                    && (empty($element['attributes']['format']) || in_array(
                        $element['attributes']['format'],
                        $input->includeOnlyFormat,
                    ))
                ) {
                    $responseIds[] = $element['attributes']['responseIdentifier'];
                }
            }
            $totalResponseCount = count($responseIds);
            if ($totalResponseCount === 0 || !$input->allowMultipleResponseVariables && $totalResponseCount > 1) {
                continue;
            }

            $testMap = $testMap->withItem(
                new Item(
                    $item->getIdentifier(),
                    $item->getTitle(),
                    $responseIds,
                ),
            );
        }

        return $testMap;
    }

    private function createSessionAccessor(FlatMapSearchInput $input): TestSessionAccessor
    {
        return $this->testSessionAccessorFactory->create($input->delivery);
    }
}
