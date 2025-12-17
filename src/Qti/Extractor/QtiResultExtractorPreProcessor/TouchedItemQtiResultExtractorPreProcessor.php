<?php

// SPDX-FileCopyrightText: 2012-2026 Open Assessment Technologies S.A.
// Copyright (C) 2023 (original work) Open Assessment Technologies SA;
//
// SPDX-License-Identifier: AGPL-3.0-only OR LicenseRef-TAO-Commercial-License

declare(strict_types=1);

namespace App\Qti\Extractor\QtiResultExtractorPreProcessor;

use App\Domain\DeliveryExecution\Model\DeliveryExecution;
use JsonException;
use App\Environment\FeatureFlagAdapterInterface;
use Psr\Log\LoggerInterface;
use qtism\common\datatypes\QtiBoolean;
use qtism\common\enums\BaseType;
use qtism\common\enums\Cardinality;
use qtism\runtime\common\OutcomeVariable;
use qtism\runtime\tests\AssessmentTestSession;

class TouchedItemQtiResultExtractorPreProcessor implements QtiResultExtractorPreProcessorInterface
{
    private const FEATURE_FLAG_TOUCHED_CUSTOM_OUTCOME_VARIABLE = 'ADD_CUSTOM_TOUCHED_OUTCOME_VARIABLE_TO_RESULT';
    private const TOUCHED_OUTCOME_VARIABLE_NAME = 'TOUCHED';
    private const ITEM_STATE_TOUCHED_PARAM_NAME = 'touched';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly FeatureFlagAdapterInterface $featureFlagAdapter,
    ) {
    }

    public function process(DeliveryExecution $deliveryExecution, AssessmentTestSession $testSession): void
    {
        $featureFlag = $this->featureFlagAdapter->isEnabled($deliveryExecution->getTenantId(), self::FEATURE_FLAG_TOUCHED_CUSTOM_OUTCOME_VARIABLE);
        if (!$featureFlag) {
            return;
        }

        $routeItems = $testSession->getRoute()->getAllRouteItems();

        foreach ($routeItems as $routeItem) {
            $isItemTouched = false;
            $itemIdentifier = $routeItem->getAssessmentItemRef()->getIdentifier();
            $itemState = $deliveryExecution->getExtraStateData()->getItemState($itemIdentifier);
            $itemSessions = $testSession->getAssessmentItemSessions($itemIdentifier);

            if (!$itemSessions || !$itemSessions->valid()) {
                continue;
            }

            $currentItemSession = $itemSessions->current();

            try {
                $decodedItemState = json_decode($itemState ?? '', true, 512, JSON_THROW_ON_ERROR);
                $isItemTouched = $decodedItemState[self::ITEM_STATE_TOUCHED_PARAM_NAME] ?? false;
            } catch (JsonException) {
                // do nothing, we will not set the touched variable
            }

            $currentItemSession->setVariable(new OutcomeVariable(
                self::TOUCHED_OUTCOME_VARIABLE_NAME,
                Cardinality::SINGLE,
                BaseType::BOOLEAN,
                new QtiBoolean($isItemTouched),
            ));
        }
    }
}
