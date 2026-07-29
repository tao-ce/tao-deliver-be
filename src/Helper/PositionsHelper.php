<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2024-2026 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Helper;

use qtism\data\AssessmentItemRef;
use qtism\runtime\tests\AssessmentTestSession;

class PositionsHelper
{
    public static function getPositionData(AssessmentTestSession $testSession): array
    {
        $route = $testSession->getRoute();
        $currentItemPosition = $testSession->getRoute()->getPosition();
        if (!$route->valid()) {
            $currentItemPosition--;
        }

        $currentRouteItem = $route->getRouteItemAt($currentItemPosition);

        $testParts = $testSession->getAssessmentTest()->getTestParts();
        $isRequiringTestPart = count($testParts) > 1;

        $itemsAmount = $informationalItemsAmount = 0;
        $data = [
            'informationalIndex' => 0,
            'item' => 0,
        ];

        foreach ($testSession->getRoute()->getAllRouteItems() as $position => $routeItem) {
            $item = $routeItem->getAssessmentItemRef();
            if (PositionsHelper::isItemInformational($item)) {
                $informationalItemsAmount++;

                if ($position === $currentItemPosition) {
                    $data['informationalIndex'] = $informationalItemsAmount;
                }
            } else {
                $itemsAmount++;

                if ($position === $currentItemPosition) {
                    $data['item'] = $itemsAmount;
                }
            }
        }

        if ($isRequiringTestPart) {
            $data['part'] = array_search(
                $currentRouteItem->getTestPart(),
                array_values(iterator_to_array($testParts)),
                true,
            ) + 1;
        }

        $data['total'] = $itemsAmount;

        return $data;
    }

    public static function getPositionDetails(AssessmentTestSession $testSession): array
    {
        $route = $testSession->getRoute();

        $currentRouteItem = $route->valid() ? $route->current() : $route->getPrevious();

        return [
            'item' => [
                'id' => $currentRouteItem->getAssessmentItemRef()->getIdentifier(),
            ],
            'section' => [
                'id' => $currentRouteItem->getAssessmentSection()->getIdentifier(),
            ],
            'part' => [
                'id' => $currentRouteItem->getTestPart()->getIdentifier(),
            ],
        ];
    }

    public static function isItemInformational(AssessmentItemRef $itemRef): bool
    {
        return empty($itemRef->getResponseDeclarations()->getArrayCopy())
            || in_array('x-tao-itemusage-informational', $itemRef->getCategories()->getArrayCopy());
    }
}
